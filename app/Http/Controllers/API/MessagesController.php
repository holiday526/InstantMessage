<?php

namespace App\Http\Controllers\API;

use Aws\Kms\KmsClient;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use App\UserKey;
use Aws\Exception\AwsException;
use App\User;
use Illuminate\Support\Facades\Auth;

class MessagesController extends Controller
{
    //
    private function getKmsClient() {
        return new KmsClient(Config::get('constants.kmsClient'));
    }

    /*
     * $result include
     * $result['CiphertextBlob']
     * $result['Plaintext']
     * $result['KeyId']
     * */
    private function genDataKey($fromUserId) {
        $kmsClient = $this->getKmsClient();
        $fromUser = User::find($fromUserId);
        $result = null;
        try {
            $result = $kmsClient->generateDataKey([
                'KeyId' => $fromUser['aws_key_id'],
                'KeySpec' => 'AES_256',
            ]);
        } catch (AwsException $e) {
            echo $e->getMessage();
            echo '\n';
        }
        return $result;
    }

    /*
     * To decrypt an encrypted data key,
     * and then immediately re-encrypt the data key
     * under a different customer master key (CMK)
     * */
    /*
     * "CiphertextBlob": blob,
     * "KeyId": "string",
     * "SourceKeyId": "string"
     * */
    private function reEncryptDataKey($client_aws_key_id, $ciphertextBlob) {
        $kmsClient = $this->getKmsClient();
        $result = $kmsClient->reEncrypt([
            'CiphertextBlob' => $ciphertextBlob,
            'DestinationKeyId' => $client_aws_key_id
        ]);
        return $result;
    }

    /*
     * require $fromUser and $toUser
     * check the db first, if no chat before then generate key
     * set the session key into db
     * */
    public function setSessionKey(Request $request) {
        $user = Auth::user();
        $from_user_id = $user['id'];
        $to_user_id = $request['to_user_id'];
        if (
            !(UserKey::where('from', $from_user_id)->where('to', $to_user_id)->orderBy('updated_at', 'desc')->first())
            or !(UserKey::where('to', $from_user_id)->where('from', $to_user_id)->orderBy('updated_at', 'desc')->first())
        ) {
            // ** no chat before
            $from_user_result = $this->genDataKey($from_user_id);
            $to_user_result =
                $this->reEncryptDataKey(User::find($to_user_id)['aws_key_id'], $from_user_result['CiphertextBlob']);
            // insert into user_keys
            $from_result = new UserKey();
            $from_result->from = $from_user_id;
            $from_result->to = $to_user_id;
            $from_result->ciphertext_blob = $from_user_result['CiphertextBlob'];
            $from_result->save();

            $to_result = new UserKey();
            $to_result->from = $to_user_id;
            $to_result->to = $from_user_id;
            $to_result->ciphertext_blob = $to_user_result['CiphertextBlob'];
            $to_result->save();

            return response(['success'=>true], Config::get('constants.status.created'));
        }
    }

    /*
     * first get the encrypted data key in blob
     * then decrypt the data key
     * return the client with client data key in plaintext
     * */
    public function getDataKey(Request $request) {
        $user = Auth::user();
        $from = $user['id'];
        $to = $request['to_user_id'];
        $session_start_by_from_user =
            UserKey::where('from',$from)
            ->where('to',$to)
            ->orderBy('updated_at', 'desc')
            ->first();
        $session_key_blob = $session_start_by_from_user->ciphertext_blob;
        $kmsClient = $this->getKmsClient();
        $result = $kmsClient->decrypt([
            'CiphertextBlob' => $session_key_blob
        ]);
        $plaintext = $result['Plaintext'];
        return response(['plaintext_base64'=>base64_encode($plaintext)]);
    }
}
