<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Config;
use Aws\Kms\KmsClient;
use Aws\Exception\AwsException;

class UserController extends Controller
{
    //
    private function getKmsClient() {
        return new KmsClient(Config::get('constants.kmsClient'));
    }

    private function getKey($aws_key_id) {
        $kmsClient = $this->getKmsClient();

        $result = $kmsClient->describeKey([
            'KeyId' => $aws_key_id,
        ]);

//        dd($result['KeyMetadata']);
        return $result['KeyMetadata'];
    }

    private function generateCmkKey($desc) {
        $kmsClient = $this->getKmsClient();
        try {
            $result = $kmsClient->createKey([
                'Description' => $desc,
                'KeyUsage' => 'ENCRYPT_DECRYPT',
            ]);
            return $result['KeyMetadata']['KeyId'];
        } catch (AwsException $e) {
            echo $e->getMessage();
            echo '\n';
        }
    }

    /**
     * login api
     *
     * @return \Illuminate\Http\Response
     */
    public function login()
    {
        if (Auth::attempt(['email' => request('email'), 'password' => request('password')])) {
            $user = Auth::user();
            $success['token'] =  $user->createToken('MyApp')->accessToken;
            $success['key'] = $this->getKey($user->aws_key_id);
            $success['id'] = $user['id'];
            return response()->json(['success' => $success], Config::get('constants.status.ok'));
        } else {
            return response()->json(['error' => 'Unauthorised'], Config::get('constants.status.unauthorized'));
        }
    }

    /**
     * Register api
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required',
            'c_password' => 'required|same:password',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], Config::get('constants.status.unauthorized'));
        }
        $input = $request->all();
        $input['password'] = bcrypt($input['password']);
        $aws_key_id = (string) $this->generateCmkKey($request->email);
        $input['aws_key_id'] = $aws_key_id;
        $user = User::create($input);
        $success['token'] =  $user->createToken('MyApp')->accessToken;
        $success['name'] =  $user->name;
        $success['aws_key_id'] = $aws_key_id;
        return response()->json(['success' => $success], Config::get('constants.status.ok'));
    }

    public function getEmail(Request $request) {
        $user = User::find($request['to_user_id']);
        return response()->json(['email'=>$user['email']]);
    }

    public function getUserIdByEmail(Request $request) {
        $user = User::where('email', $request['email'])->first();
        return response()->json(['user_id'=>$user['id']]);
    }

}
