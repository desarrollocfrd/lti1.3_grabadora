<?php 
namespace xcesaralejandro\lti1p3\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use xcesaralejandro\lti1p3\Facades\JWT;
use xcesaralejandro\lti1p3\Facades\Launch;
use xcesaralejandro\lti1p3\Http\Requests\LaunchRequest;
use xcesaralejandro\lti1p3\Models\Nonce;
use xcesaralejandro\lti1p3\Models\User;
use GuzzleHttp\Psr7\Request;
use Ramsey\Uuid\Uuid;
use xcesaralejandro\lti1p3\Classes\Message;
use xcesaralejandro\lti1p3\DataStructure\DeepLinkingInstance;
use xcesaralejandro\lti1p3\DataStructure\ResourceLinkInstance;
use xcesaralejandro\lti1p3\Models\Instance;

class Lti1p3Controller {
    public function onResourceLinkRequest(string $instance_id) : mixed {
        $instance = Instance::RecoveryFromId($instance_id);
        return View('lti1p3::examples.resource_link_request_launched')->with(['instance' => $instance, 'instance_id' => $instance_id]);
    }

    public function onDeepLinkingRequest(string $instance_id) : mixed {
        return View('lti1p3::examples.deep_linking_request_builder')->with(['instance_id' => $instance_id]);
    }

    public function onError(mixed $exception = null) : mixed {
        return throw new \Exception($exception);
    }

    public function launchConnection(LaunchRequest $request) : mixed {
        try{
            if(Launch::isLoginHint($request)) {
                return Launch::attemptLogin($request);
            } else if (Launch::isSuccessfully($request)) {
                $message = new Message($request->id_token, $request->state);
                if($message->isDeepLinking()){
                    $instance = Launch::syncDeepLinkingRequest($message);
                    $instance->request = $request->all();
                    $instance_id = Launch::storeInstance($instance);
                    return $this->onDeepLinkingRequest($instance_id);
                }else if($message->isResourceLink()){
                    $instance = Launch::syncResourceLinkRequest($message);
                    $instance->request = $request->all();
                    $content = $message->getContent();
                    $instance_id = Launch::storeInstance($instance);
                    if($content->hasTargetLinkUriRedirection()){
                        redirect($content->getTargetLinkUri());
                    }
                    return $this->onResourceLinkRequest($instance_id);
                }else{
                    $this->onError("Lti message type is not supported."); 
                }
            } else {
                return $this->onError();
            }
        }catch(\Exception $exception){
            $this->onError($exception);
        }
    }

    public function jwks(){
        $private_key = config('lti1p3.PRIVATE_KEY');
        $signature_method = config('lti1p3.SIGNATURE_METHOD');
        $kid = config('lti1p3.KID');
        $keys = JWT::buildJWKS($private_key, $signature_method, $kid);
        return response()->json($keys);
    }
}