<?php


namespace ComeenPlay\LumApps\GoogleAccount;


use App\ImageIcon;
use App\Models\Account;
use App\Models\Space;
use Barryvdh\Reflection\DocBlock\Type\Collection;
use Cache;
use Carbon\Carbon;
use ComeenPlay\LumApps\Account\LumAppsBaseAuthProviderHandler;
use Exception;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Session;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use ComeenPlay\SdkPhp\Handlers\OAuthProviderHandler;
use ComeenPlay\SdkPhp\Interfaces\IModule;
use Google_Client;

class LumAppsGoogleAuthProviderHandler extends LumAppsBaseAuthProviderHandler
{
    protected static string $provider = 'lumapps-google-account';

    public function getUserInfos($config)
    {
        $client = $this->getClient($config);
        $google_oauth = new \Google_Service_Oauth2($client);

        return $google_oauth->userinfo->get();
    }

    public function signin($config) {
        $client = $this->getClient();

        // $ds_uuid = (string)Str::uuid();
        // Session::put($ds_uuid, compact('space_name', 'account_id'));

        $client->setRedirectUri(Str::replace('http:', 'https:', route('api.oauth.callback')));
        // $client->setState($ds_uuid);
        $client->setApprovalPrompt('force');

        return $client->createAuthUrl();
    }

    public function callback($request, $redirectUrl = null) {
        $state = Session::get($request->input('state'));
        // $account = $this->extractAccount($state['account_id']);
        // $space_name = $state['space_name'];
        $code = $request->input('code');

        $client = $this->getClient();
        $client->setAccessType('offline');
        $client->fetchAccessTokenWithAuthCode($code);

        $access_token = $client->getAccessToken();
        $data = $this->processOptions($access_token);
        $dataStr = json_encode($data);

        return redirect()->away($redirectUrl ."&data=$dataStr");
    }

    private function getClient($account = null): \Google_Client
    {
        $client = new \Google_Client();
        $client->setApplicationName(config('services.lumapps.app_name'));
        $client->setClientId(config('services.lumapps.client_id'));
        $client->setClientSecret(config('services.lumapps.client_secret'));
        $client->setAccessType('offline');

        $client->setRedirectUri(Str::replace('http:', 'https:', route('api.oauth.callback')));

        if (isset($account) && Arr::get($account, 'options.access_token')) {
            $client->setAccessToken(Arr::get($account, 'options'));

            if ($client->isAccessTokenExpired()) {
                $refresh_token = $client->getRefreshToken();

                $new_access = $client->fetchAccessTokenWithRefreshToken($refresh_token);
                Arr::set($account, 'options.access_token', $this->processOptions($new_access));

                $client->setAccessToken($new_access);
            }
        }

        foreach ($this->getScopes() as $scope) {
            $client->addScope($scope);
        }

        return $client;
    }

    private function getScopes(): array
    {
        return [
            'https://www.googleapis.com/auth/userinfo.email'
        ];
    }

    public function getAccessToken($account = null)
    {
        $driver = $this->getClient($account);
        return $driver->getAccessToken()['access_token'];
    }

    public function getValidations($options = null): array
    {
        return [
            'rules' => [
                'endpoint_uri' => ['required']
            ],
            'messages' => [
                // 'url.required' => ""
            ],
        ];
    }
}
