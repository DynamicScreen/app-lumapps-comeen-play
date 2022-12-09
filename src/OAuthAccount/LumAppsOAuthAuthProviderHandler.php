<?php


namespace ComeenPlay\LumApps\OAuthAccount;


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

class LumAppsOAuthAuthProviderHandler extends LumAppsBaseAuthProviderHandler
{
    protected static string $provider = 'lumapps-oauth-account';


    public function getUserInfos($account)
    {
        $this->getAccessToken($account);
    }

    public function signin($callbackUrl)
    {
        $tmp_uuid = (string)Str::uuid();
        $options = json_decode(request()->input('options'));
        Session::put($tmp_uuid, $options);

        return Str::replace('http:', 'https:', route('api.oauth.callback')) . "?state=$tmp_uuid";
        // return route('manager.settings.accounts.create.step2', ['space_name' => $space_name, 'account' => $account]);
    }

    public function getOptionsValidator($options)
    {
        return $this->optionsValidator($this->makeValidator($options), $options);
    }

    public function optionsValidator(\Illuminate\Validation\Validator $validator, array $options)
    {
        $validator->addRules([
            'host' => 'required',
            'organization_id' => 'required',
            'client_id' => 'required',
            'client_secret' => 'required',
            'account_id' => '', // save the account id (as hidden input) to retrieve it during token request
        ]);

        $validator->addCustomAttributes([
            'update_options' => '',
        ]);

        return $validator;
    }

    protected final function makeValidator(array $values = [])
    {
        return app('validator')->make($values, []);
    }

    public function callback($request, $redirectUrl = null) {
        $tmp_uuid = $request->input('state');
        $options = Session::get($tmp_uuid, []);
        $options = $this->requestAccessToken($options, true);

        return redirect()->away($redirectUrl . "&data=" . json_encode($options));
    }

    public function getAccessToken($account = null)
    {
        $data = collect($this->requestAccessToken($account['options']));

        if ($data->isEmpty() || !$data->get('access_token')) {
            return null;
        }

        return $data->get('access_token');
    }

    // public function processOptions($options)
    // {
    //     $account = Account::find($options['account_id']);

    //     $access_token = $this->requestAccessToken($account, $options);
    //     if ($access_token) {
    //         return $access_token;
    //     }

    //     // Stay on the same page, and notify the user that an error has been throws
    //     throw new HttpResponseException(redirect(route('manager.settings.accounts.edit', ['_spacename' => $account->space->name, 'account' => $account]))->with('error', __('settings/accounts.lumapps_oauth_options.error')));
    // }

    private function requestAccessToken($options, $returnFullOptions = false) {
        if (!$options) {
            return null;
        }

        $options = collect($options);

        $access_token_data = collect($options->get('access_token_data'));

        // Avoid recalling access_token request
        if ($access_token_data) {
            if (!Carbon::now()->greaterThan(Carbon::createFromTimestamp($access_token_data['expires_in'] - 60*15))) {
                return $access_token_data;
            }
        }

        // Get account options
        $host = $options->get("host");
        $organization_id = $options->get("organization_id");
        $client_id = $options->get("client_id");
        $client_secret = $options->get("client_secret");

        // If one of these options is null we can't continue
        if (!$host || !$organization_id || !$client_id || !$client_secret) {
            return null;
        }

        $auth = base64_encode("$client_id:$client_secret");

        $client = new Client();
        try {
            $response = $client->post("$host/v2/organizations/$organization_id/application-token", [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Basic ' . $auth,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'grant_type' => 'client_credentials',
                ]
            ]);

            // Change 'expire_in' format
            $access_token_data = json_decode($response->getBody()->getContents());
            $access_token_data->expires_in = Carbon::now()->addSeconds($access_token_data->expires_in)->timestamp;


            if ($response->getStatusCode() === 200) {
                $options->put("access_token_data", $access_token_data);
                $options->put("update_options", true);

                if ($returnFullOptions) {
                    return $options;
                } else {
                    return $access_token_data;
                }
            }

            if ($returnFullOptions) {
                return $options;
            } else {
                return $access_token_data;
            }
        } catch (\Exception $e) {
            // activity('debug')->withProperties(['error' => $e->getMessage()])
            //     ->log('Error on Lumapps OAuth access token request');

            if ($returnFullOptions) {
                return $options;
            } else {
                return $access_token_data;
            }
        }
    }

    public function getValidations($options = null): array
    {
        return [
            'rules' => [
                'endpoint_uri' => ['required'],
                'host' => ['required'],
                'organization_id' => ['required'],
                'client_id' => ['required'],
                'client_secret' => ['required'],
            ],
            'messages' => [
                // 'url.required' => ""
            ],
        ];
    }
}
