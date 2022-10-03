<?php


namespace ComeenPlay\LumApps\Account;


use App\ImageIcon;
use App\Models\Account;
use App\Models\Space;
use Barryvdh\Reflection\DocBlock\Type\Collection;
use Cache;
use Carbon\Carbon;
use Exception;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Session;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use ComeenPlay\SdkPhp\Handlers\OAuthProviderHandler;
use ComeenPlay\SdkPhp\Interfaces\IModule;

abstract class LumAppsBaseAuthProviderHandler extends OAuthProviderHandler
{
    public function __construct(IModule $module, $config = null)
    {
        parent::__construct($module, $config);
    }

    public function provideData($settings = [])
    {
        $this->addData('me', fn () => $this->getUserInfos($settings));
        $this->addData('test-auth', fn () => $this->testConnection($settings));
    }

    public function provideRemoteMethods()
    {
        $this->addRemoteMethod('freshNews', function ($parameters, $details) {
            /** @var Account $display */
            $account = Arr::get($details, 'account');

            $instanceId = Arr::get($parameters, 'instance');
            $lang = Arr::get($parameters, 'lang');

            return $this->getFreshNews($account, $instanceId, $lang);
        });
        $this->addRemoteMethod('news', function ($parameters, $details) {
            /** @var Account $display */
            $account = Arr::get($details, 'account');

            $instanceId = Arr::get($parameters, 'instance');
            $lang = Arr::get($parameters, 'lang');

            return $this->getNews($account, $instanceId, $lang);
        });
        $this->addRemoteMethod('getInstances', function ($parameters, $details) {
            /** @var Account $display */
            $account = Arr::get($details, 'account');

            return $this->getInstances($account);
        });
        $this->addRemoteMethod('getCustomContentTypes', function ($parameters, $details) {
            /** @var Account $display */
            $account = Arr::get($details, 'account');

            $instanceId = Arr::get($parameters, 'instanceId');

            $types = $this->getCustomContentTypes($account, $instanceId);

            return $types->map(function($type) {
                if (in_array(Arr::get($type, 'name.en'), ['News'])) {
                    return [
                        "id" => "news",
                        "name" => $type['name']
                    ];
                }
                return $type;
            });
        });

        $this->addRemoteMethod('freshCustomContent', function ($parameters, $details) {
            /** @var Account $display */
            $account = Arr::get($details, 'account');

            $instanceId = Arr::get($parameters, 'instance');
            $contentType = Arr::get($parameters, 'contentType');
            $lang = Arr::get($parameters, 'lang');

            return $this->getFreshCustomContent($account, $instanceId, $contentType, $lang);
        });
        $this->addRemoteMethod('customContent', function ($parameters, $details) {
            /** @var Account $display */
            $account = Arr::get($details, 'account');

            $instanceId = Arr::get($parameters, 'instance');
            $contentType = Arr::get($parameters, 'contentType');
            $lang = Arr::get($parameters, 'lang');

            return $this->getCustomContent($account, $instanceId, $contentType, $lang);
        });
    }

    public function testConnection($config)
    {
        try {
            $this->getUserInfos($config);
            return response('', 200);
        } catch (\Exception $e) {
            return response('Connection failed', 403);
        }
    }

    public function get($uri, $query_params, $config = null)
    {
        $access_token = $this->getAccessToken($config);

        $client = new Client();
        try {
            $response = $client->request('GET', $this->endpoint_uri(Arr::get($config, 'options')) . Str::start($uri, '/'), [
                'query' => $query_params,
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                ],
            ]);
        } catch (Exception $ex) {
            return [];
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    public function post($uri, $query_params, $config = null)
    {
        $access_token = $this->getAccessToken($config);

        $client = new Client();
        try {
            $response = $client->request('POST', $this->endpoint_uri(Arr::get($config, 'options')) . Str::start($uri, '/'), [
                'query' => $query_params,
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                ],
            ]);
        } catch (Exception $ex) {
            dump($ex);
            return [];
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    public function endpoint_uri($options = null)
    {
        // TODO
        // $space = current_space();

        // if (!$space) {
        //     $space = Space::find($account->space_id);
        // }

        // $settings = $space->getSettingsForExtension('dynamicscreen.lumapps');
        return Arr::get($options, 'endpoint_uri');
    }

    public abstract function getUserInfos($config);

    public function getInstances($account)
    {
        //{$account->id}
        // return Cache::remember("dynamicscreen.lumapps::getInstances:{$this->endpoint_uri}, TODO", Carbon::now()->addWeek(), function () {
            $instances = collect();
            $response = collect();
            $nbRequest = 0;

            do {
                $response = collect($this->get('instance/list', [
                    'cursor' => $response->get('cursor'),
                    'maxResults' => '1000', // MAX LIMIT 1000
                    'fields' => 'items(id,title,langs,defaultLang),cursor,more',
                ], $account));

                $instances = $instances->merge($response->get('items'));
                $nbRequest++;
            } while ($response->get('cursor') && $nbRequest < 10);

            return $instances->filter(function ($instance) {
                return !empty(Arr::get($instance, 'title'));
            });
        // });
    }

    public function getCustomContentTypes($account, $instanceId) {
        $customContents = collect();
        $response = collect();
        $nbRequest = 0;

        do {
            $response = collect($this->get('customcontenttype/list', [
                'cursor' => $response->get('cursor'),
                'instance' => $instanceId,
                'includeInstanceSiblings' => false,
                'maxResults' => '100',
                'fields' => 'items(id, name),cursor',
            ], $account));

            $customContents = $customContents->merge($response->get('items'));
            $nbRequest++;
        } while ($response->get('cursor') && $nbRequest < 10);

        return $customContents;
    }

    public function getFreshNews($account, $instanceId, $lang)
    {
        $data = [];
        $nbRequest = 0;
        $response = collect();
        $fields = 'items(uid,canonicalUrl,status,template(components),mediaThumbnail(thumbnail),title,excerpt,content,metadata,customContentTypeTags,customContentTypeDetails(tags),createdAt,updatedAt,comments,likes,authorDetails(fullName)),cursor,more';

        do {
            $nbRequest++;
            $response = collect($this->post('content/list', [
                'cursor' => $response->get('cursor'),
                'lang' => $lang,
                'instanceId' => $instanceId,
                'type' => 'news',
                'maxResults' => '150',
                'fields' => $fields,
                'sortOrder' => "-updatedAt",
                'more' => 'true',
                'read_timeout' => 300,
            ], $account));

            $items = $this->fetchUrl($lang, $response->get('items', []));

            $data = array_merge($items, $data);
        } while ($response->get('more') && $nbRequest < 5); // migth be changed but this limit is enough


        return $data;
    }

    public function getFreshCustomContent($account, $instanceId, $customContentId, $lang) {
        $nbRequest = 0;
        $response = collect();
        $fields = 'items(uid, canonicalUrl, title, author, thumbnail, customContentTypeTags, customContentTypeDetails, metadata, updatedAt, type, status, template),cursor,more';
        $data = [];

        do {
            $nbRequest++;
            $response = collect($this->post('content/list', [
                'cursor' => $response->get('cursor'),
                'lang' => $lang,
                'instanceId' => $instanceId,
                'customContentType' => $customContentId,
                'maxResults' => '30',
                'fields' => $fields,
                'sortOrder' => "-updatedAt",
                'more' => 'true',
                'read_timeout' => 300,
            ], $account));

            $data = array_merge($data, $response->get('items', []));
        } while ($response->get('more') && $nbRequest < 2); // migth be changed but this limit is enough

        return $data;
    }

    public function getMetadataContent($instanceId, $lang) {
        $nbRequest = 0;
        $response = collect();
        $fields = 'items(id, familyKey, name),cursor,more';
        $data = [];

        do {
            $nbRequest++;
            $response = collect($this->get('metadata/list', [
                'cursor' => $response->get('cursor'),
                'more' => 'true',
                'instance' => $instanceId,
                'fields' => $fields,
                'read_timeout' => 300,
                'maxResults' => '100',
            ]));

            $data = array_merge($data, $response->get('items', []));
        } while ($response->get('more') && $nbRequest < 5); // migth be changed but this limit is enough

        return $data;
    }

    public function fetchUrl($lang, $items)
    {
        foreach ($items as &$item) {
            if ($item['canonicalUrl']) {
                $url = Arr::get($item['canonicalUrl'], $lang, "");
                $item['canonicalUrl'] = $url;
            }
        }
        return $items;
    }

    public function getNews($account, $instanceId, $lang)
    {
        // $lang = $lang ?? Auth::user()->locale;

        return $this->getFreshNews($account, $instanceId, $lang);
    }

    public function getCustomContent($account, $instanceId, $customContentId, $lang)
    {
        // $lang = $lang ?? Auth::user()->locale;

        return $this->getFreshCustomContent($account, $instanceId, $customContentId, $lang);
    }

    public function getCategories($instanceId, $contentType)
    {
        // return Cache::remember("dynamicscreen.lumapps::getTags:{$this->endpoint_uri()}, {$account->id}, {$instanceId}", Carbon::now()->addHours(1), function () use ($account, $instanceId, $lang) {
        return dd($this->get('tag/list', [
            'instance' => $instanceId,
            'kind' => 'all',
            'maxResults' => '199', // MAX LIMIT 1000s
        ]));
        // });
    }

    public function getContents($instanceId, $lang)
    {
        // {$account->id}
        return Cache::remember("dynamicscreen.lumapps::getContents:{$this->endpoint_uri()}, TODO, {$instanceId}", Carbon::now()->addHours(1), function () use ($instanceId, $lang) {
            return $this->post('content/list', [
                'lang' => $lang,
                'instanceId' => $instanceId,
                'maxResults' => '199', // MAX LIMIT 1000
            ]);
        });
    }

    public function getCommunities($config, $instanceId, $lang)
    {
        return Cache::remember("dynamicscreen.lumapps::getCommunities:{$this->endpoint_uri()}, TODO, {$instanceId}", Carbon::now()->addHours(1), function () use ($config, $instanceId, $lang) {
            $data = [];
            $nbRequest = 0;
            $response = collect();

            $fields = 'items(id,title),more,cursor';

            do {
                $nbRequest++;
                $response = collect($this->post('search/community', [
                    'lang' => $lang,
                    'instance' => $instanceId,
                    'customer' => $this->getUserId($config),
                    'more' => 'true',
                    'maxResults' => '100',
                    'fields' => $fields
                ]));
                $data = array_merge($response->get('items', []), $data);
            } while ($response->get('more') && $nbRequest <= 5);


            return $data;
        });

    }

    public function getCommunityPosts($config, $instanceId, $communityId, $lang)
    {
        return Cache::remember("dynamicscreen.lumapps::getCommunityPosts:{$this->endpoint_uri($config)}, TODO, {$instanceId}, {$communityId}", Carbon::now()->addHours(1),
            function () use ($config, $instanceId, $communityId, $lang) {
                $data = [];
                $nbRequest = 0;
                $response = collect();
                $fields = 'items(uid,status,title,excerpt,content,tagsDetails,createdAt,updatedAt,comments,likes,authorDetails(fullName)),cursor,more';
                do {
                    $nbRequest++;

                    $response = collect($this->post('search/post', [
                        'lang' => $lang,
                        'instanceId' => $instanceId,
                        'customer' => $this->getUserId($config),
                        'externalKey' => $communityId,
                        'more' => 'true',
                        'maxResults' => '100',
                        // 'fields' => $fields,
                    ]));

                    $data = array_merge($response->get('items', []), $data);
                } while ($response->get('more') && $nbRequest <= 5);
                return $data;
            });
    }

    private function getUserId($config)
    {
        $email = $this->getUserInfos($config)->email;

        return collect($this->get('user/get', [
            'email' => $email,
            'fields' => 'id'
        ]))->get('id');


    }

    public function getJobOffers($config, $lang)
    {
        $result = $this->get('job-offer/list', [
            'lang' => $lang,
        ]);
        return $result;
    }

    public function getPage($config, $instanceId, $lang)
    {
        $result = $this->get('content/list', [
            'lang' => $lang,
            'instanceId' => $instanceId,
            'type' => 'page',
            'maxResults' => '100',
        ]);
        return $result;
    }

    public function getPages($config, $instanceId, $contentId, $lang)
    {
        return Cache::remember("dynamicscreen.lumapps::getPages:{$this->endpoint_uri($config)}, {$instanceId}", Carbon::now()->addHours(1),
            function () use ($config, $instanceId, $lang) {
                $data = [];
                $nbRequest = 0;
                $response = collect();
                $fields = 'items(uid,status,title,excerpt,content,tagsDetails,createdAt,updatedAt,comments,likes,authorDetails(fullName)),cursor,more';

                do {
                    $nbRequest++;

                    $response = collect($this->get('content/get', [
                        'lang' => $lang,
                        'instanceId' => $instanceId,
                        'type' => 'page',
                        'more' => 'true',
                        'maxResults' => '100',
                        // 'fields' => $fields,
                    ]));

                    $data = array_merge($response->get('items', []), $data);
                } while ($response->get('more') && $nbRequest <= 5);

                return $data;
            });
    }

    public function getEvents($config, $lang)
    {
        $result = $this->get('event/list', [
            'lang' => $lang,
        ]);
        return $result;
    }


    public abstract function signin($config);

    public abstract function callback($request, $redirect_uri = null);

    public abstract function getAccessToken($account = null);
}
