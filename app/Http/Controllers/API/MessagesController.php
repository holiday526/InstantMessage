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
use App\Message;
use Pusher\Pusher;

class MessagesController extends Controller
{
    //
    private function getKmsClient() {
        return new KmsClient(Config::get('constants.kmsClient'));
    }


    private function generateRandomString($length = 64) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
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
            $hmac_key = $this->generateRandomString();

            $from_user_result = $this->genDataKey($from_user_id);
            $to_user_result =
                $this->reEncryptDataKey(User::find($to_user_id)['aws_key_id'], $from_user_result['CiphertextBlob']);
            // insert into user_keys
            $from_result = new UserKey();
            $from_result->from = $from_user_id;
            $from_result->to = $to_user_id;
            $from_result->ciphertext_blob = $from_user_result['CiphertextBlob'];
            $from_result->hmac_key = $hmac_key;
            $from_result->save();

            $to_result = new UserKey();
            $to_result->from = $to_user_id;
            $to_result->to = $from_user_id;
            $to_result->ciphertext_blob = $to_user_result['CiphertextBlob'];
            $to_result->hmac_key = $hmac_key;
            $to_result->save();

            $datakey_plaintext = $this->getDataKey($request);

            return response(['success'=>true, 'plaintext_base64'=>base64_encode($datakey_plaintext)], Config::get('constants.status.created'));
        } else {
            return $this->getDataKey($request);
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

    /*
     * return hmac_key
     * */
    public function getHmacKey(Request $request) {
        $user = Auth::user();
        $from = $user['id'];
        $to = $request['to_user_id'];
        $session_start_by_from_user = UserKey::where('from',$from)
            ->where('to',$to)
            ->orderBy('updated_at', 'desc')
            ->first();
        $hmac_key = $session_start_by_from_user->hmac_key;
        return response(['hmac_key'=>$hmac_key]);
    }

    public function getMessage($user_id) {
        $user = Auth::user();
        $my_id = $user['id'];

        //when click to see message selected user's message will be read, update
        Message::where(['from'=>$user_id, 'to'=>$my_id])->update(['is_read'=>1]);

        // getting all message for the selected user
        // getting those message which is from = Auth::id() and to = user_id OR from = user_id ant to = Auth::id();
        $messages = Message::where(function ($query) use ($user_id, $my_id){
            $query->where('from', $my_id)->where('to', $user_id)->where('is_read', 0);
        })->orWhere(function ($query) use ($user_id, $my_id){
            $query->where('from', $user_id)->where('to', $my_id)->where('is_read', 0);
        })->get();
        // messages which are not read by the users
        return ['messages'=> $messages];
    }

    public function sendMessage(Request $request)
    {
        $user = Auth::user();
        $from = $user['id'];
        $to = $request->to_user_id;
        $message = $request->message;

        $data = new Message();
        $data->from = $from;
        $data->to = $to;
        $data->message = $message;
        $data->is_read = 0;
        $data->save();

        // pusher
        $options = array(
            'cluster' => 'ap1',
            'useTLS' => true
        );

        $pusher = new Pusher(
            env('PUSHER_APP_KEY'),
            env('PUSHER_APP_SECRET'),
            env('PUSHER_APP_ID'),
            $options
        );

        $data = ['from' => $from, 'to' => $to, 'message' => $message]; // sending from and to user id when pressed enter
        $pusher->trigger('my-channel', 'my-event', $data);
    }
}
