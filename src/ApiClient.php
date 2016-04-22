<?php

namespace Opteck;

use GuzzleHttp;
use Opteck\Exceptions\AssetNotFoundException;
use Opteck\Exceptions\NoAvailableOptionsException;
use Opteck\Requests\CreateLead as CreateLeadRequest;
use Opteck\Requests\GetDefinitions as GetDefinitionsRequest;
use Opteck\Requests\Trade as TradeRequest;
use Opteck\Responses\Auth as AuthResponse;
use Opteck\Responses\CreateLead as CreateLeadResponse;
use Opteck\Responses\GetAssetRate as GetAssetRateResponse;
use Opteck\Responses\GetAssets as GetAssetsResponse;
use Opteck\Responses\GetDefinitions as GetDefinitionsResponse;
use Opteck\Responses\GetDeposits as GetDepositsResponse;
use Opteck\Responses\GetLeadDetails as GetLeadDetailsResponse;
use Opteck\Responses\GetMarkets as GetMarketsResponse;
use Opteck\Responses\GetOptionTypes as GetOptionTypesResponse;
use Opteck\Responses\GetTradeActions as GetTradeActionsResponse;
use Opteck\Responses\Trade as TradeResponse;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class ApiClient implements LoggerAwareInterface
{
    /**
     * @var \GuzzleHttp\ClientInterface A Guzzle HTTP client.
     */
    protected $httpClient;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    protected $url = 'https://api.optaffiliates.com/v1';

    /**
     * @var int
     */
    protected $affiliateId;

    /**
     * @var string
     */
    protected $partnerId;

    public function __construct($affiliateId, $partnerId, $url = null)
    {
        $this->affiliateId = $affiliateId;
        $this->partnerId = $partnerId;
        if (!is_null($url)) {
            $this->url = $url;
        }
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param \Opteck\Requests\CreateLead $request
     *
     * @return \Opteck\Responses\CreateLead
     */
    public function createLead(CreateLeadRequest $request)
    {
        $data = [
            'affiliateID' => $this->affiliateId,
            'email'       => $request->getEmail(),
            'firstName'   => $request->getFirstName(),
            'lastName'    => $request->getLastName(),
            'phone'       => $request->getPhone(),
            'password'    => $request->getPassword(),
            'language'    => $request->getLanguage(),
            'country'     => $request->getCountry(),
            'ip'          => $request->getIp(),
            'campaign'    => $request->getCampaign(),
            'subcampaign' => $request->getSubCampaign(),
            'comment'     => $request->getComment(),
            'marker'      => $request->getMarker(),
        ];

        $data['checksum'] = $this->getChecksum($data);

        $payload = new Payload($this->postRequest($this->getUrl().'/lead/create', $data));
        $response = new CreateLeadResponse($payload);

        return $response;
    }

    /**
     * Creates a session on the platform and returns a token used in some requests along with lead info.
     *
     * @param string $email
     * @param string $password
     *
     * @throws \Opteck\Exceptions\LeadNotFoundException
     *
     * @return \Opteck\Responses\Auth
     */
    public function auth($email, $password)
    {
        $data = [
            'affiliateID' => $this->affiliateId,
            'email'       => $email,
            'password'    => $password,
        ];

        $data['checksum'] = $this->getChecksum($data);

        $payload = new Payload($this->postRequest($this->getUrl().'/trade/auth', $data));
        $response = new AuthResponse($payload);

        return $response;
    }

    /**
     * Returns lead information.
     *
     * @param string $email
     *
     * @return \Opteck\Responses\GetLeadDetails
     */
    public function getLeadDetails($email)
    {
        $data = [
            'affiliateID' => $this->affiliateId,
            'email'       => $email,
        ];

        $data['checksum'] = $this->getChecksum($data);

        $payload = new Payload($this->postRequest($this->getUrl().'/trade/getLeadDetails', $data));
        $response = new GetLeadDetailsResponse($payload);

        return $response;
    }

    /**
     * Returns list of deposits made in the specified date range.
     *
     * @param int $fromTimestamp UNIX timestamp
     * @param int $toTimestamp   UNIX timestamp
     *
     * @return \Opteck\Entities\Deposit[]
     */
    public function getDeposits($fromTimestamp, $toTimestamp = null)
    {
        $toTimestamp = !is_null($toTimestamp) ? $toTimestamp : time();

        $data = [
            'affiliateID' => $this->affiliateId,
            'dateFrom'    => date('c', intval($fromTimestamp)),
            'dateTo'      => date('c', intval($toTimestamp)),
        ];

        $data['checksum'] = $this->getChecksum($data);

        $payload = new Payload($this->postRequest($this->getUrl().'/lead/getDeposits', $data));
        $response = new GetDepositsResponse($payload);

        return $response->getDeposits();
    }

    /**
     * Returns the list of option types.
     *
     * @param int $optionTypeId
     *
     * @throws \Exception
     *
     * @return \Opteck\Entities\OptionType[]
     */
    public function getOptionTypes($optionTypeId = null)
    {
        $data = [
            'affiliateID' => $this->affiliateId,
        ];

        if ($optionTypeId > 0) {
            $data['optionTypeID'] = $optionTypeId;
        }

        $data['checksum'] = $this->getChecksum($data);

        $payload = new Payload($this->postRequest($this->getUrl().'/trade/getOptionTypes', $data));
        $response = new GetOptionTypesResponse($payload);

        return $response->getOptionTypes();
    }

    /**
     * @throws \Exception
     *
     * @return \Opteck\Entities\Market[]
     */
    public function getMarkets()
    {
        $data = [
            'affiliateID' => $this->affiliateId,
        ];

        $data['checksum'] = $this->getChecksum($data);

        $payload = new Payload($this->postRequest($this->getUrl().'/trade/getMarkets', $data));
        $response = new GetMarketsResponse($payload);

        return $response->getMarkets();
    }

    /**
     * @param int $marketId
     * @param int $assetId
     *
     * @throws \Exception
     *
     * @return \Opteck\Entities\Asset[]
     */
    public function getAssets($marketId = null, $assetId = null)
    {
        $data = [
            'affiliateID' => $this->affiliateId,
        ];

        if ($marketId > 0) {
            $data['marketID'] = intval($marketId);
        }

        if ($assetId > 0) {
            $data['assetID'] = intval($assetId);
        }

        $data['checksum'] = $this->getChecksum($data);

        $payload = new Payload($this->postRequest($this->getUrl().'/trade/getAssets', $data));
        $response = new GetAssetsResponse($payload);

        return $response->getAssets();
    }

    /**
     * @param \Opteck\Requests\GetDefinitions $request
     *
     * @throws \Exception
     *
     * @return \Opteck\Entities\Definition[]
     */
    public function getDefinitions(GetDefinitionsRequest $request)
    {
        $data = [
            'affiliateID' => $this->affiliateId,
        ];

        if ($request->getAssetId()) {
            $data['assetID'] = $request->getAssetId();
        }

        if ($request->getIsActive() !== null) {
            $data['isActive'] = intval($request->getIsActive());
        }

        if ($request->getOptionTypeId()) {
            $data['assetID'] = $request->getOptionTypeId();
        }

        $data['checksum'] = $this->getChecksum($data);

        $payload = new Payload($this->postRequest($this->getUrl().'/trade/getDefinitions', $data));
        $response = new GetDefinitionsResponse($payload);

        return $response->getDefinitions();
    }

    /**
     * @param int $assetId
     *
     * @throws \Exception
     *
     * @return \Opteck\Responses\GetAssetRate
     */
    public function getAssetRate($assetId)
    {
        $data = [
            'affiliateID' => $this->affiliateId,
            'assetID'     => intval($assetId),
        ];

        $data['checksum'] = $this->getChecksum($data);

        $payload = new Payload($this->postRequest($this->getUrl().'/trade/getRate', $data));

        return new GetAssetRateResponse($payload);
    }

    /**
     * Special wrapper for complex Opteck logic required for each trade.
     * You can use this method instead of procedure with Assets, Asset rate, Definitions, and authorization.
     *
     * @param string $email     User email
     * @param string $password  User password
     * @param string $symbol    Symbol name, e.g.: 'EURUSD'.
     * @param int    $direction Required position's direction. -1 - put, 1 - sell.
     * @param int    $amount    Position amount, should be at least 25$ or equivalent.
     *
     * @throws \Opteck\Exceptions\NoAvailableOptionsException
     * @throws \Opteck\Exceptions\NoEnoughBalanceException
     *
     * @return \Opteck\Responses\Trade
     */
    public function openPosition($email, $password, $symbol, $direction, $amount)
    {
        $authResponse = $this->auth($email, $password);

        $assetId = $this->resolveAssetNameToId($symbol);

        $definitions = $this->getDefinitions(new GetDefinitionsRequest([
            'isActive' => 1,
            'assetId'  => $assetId,
        ]));

        if (empty($definitions)) {
            throw new NoAvailableOptionsException(null, 'No available options for '.$symbol);
        }

        // We will use first definition, without any logic...
        $definition = $definitions[0];

        $assetRate = $this->getAssetRate($assetId);

        $response = $this->trade(new TradeRequest([
            'token'        => $authResponse->getToken(),
            'timestamp'    => $assetRate->getTimestamp(),
            'microtime'    => $assetRate->getMicrotime(),
            'definitionId' => $definition->getId(),
            'strike'       => $assetRate->getRate(),
            'amount'       => $amount,
            'direction'    => $direction,
        ]));

        return $response;
    }

    public function trade(TradeRequest $request)
    {
        $data = [
            'affiliateID'  => $this->affiliateId,
            'token'        => $request->getToken(),
            'timestamp'    => $request->getTimestamp(),
            'microtime'    => $request->getMicrotime(),
            'definitionID' => $request->getDefinitionId(),
            'strike'       => $request->getStrike(),
            'amount'       => $request->getAmount(),
            'direction'    => $request->getDirection(),
        ];

        $data['checksum'] = $this->getChecksum($data);

        $payload = new Payload($this->postRequest($this->getUrl().'/trade/action', $data));
        $response = new TradeResponse($payload);

        return $response;
    }

    /**
     * Returns user's trades (opened and closed).
     *
     * @param string $email
     * @param int    $timeFrom
     * @param int    $timeTo
     *
     * @return Entities\TradeAction[]
     *
     * @throws \Exception
     */
    public function getTradeActions($email, $timeFrom = null, $timeTo = null)
    {
        if (is_null($timeFrom)) {
            $timeFrom = strtotime('-1 week');
        }

        if (is_null($timeTo)) {
            $timeTo = time();
        }

        $data = [
            'affiliateID' => $this->affiliateId,
            'email'       => $email,
            'dateFrom'    => date('c', $timeFrom),
            'dateTo'      => date('c', $timeTo),
        ];

        $data['checksum'] = $this->getChecksum($data);

        $payload = new Payload($this->postRequest($this->getUrl().'/trade/instances', $data));
        $response = new GetTradeActionsResponse($payload);

        return $response->getTradeActions();
    }

    /**
     * Returns array with ISO 3166-1 codes for countries forbidden for signup.
     *
     * @return array
     */
    public function getForbiddenCountries()
    {
        return [
            'IL',
            'US',
            'IQ',
            'NG',
            'DZ',
            'MA',
            'SD',
            'LY',
            'YE',
            'BW',
            'TN',
            'SY',
            'UM',
            'VI',
            'IR',
            'KP',
            'CU',
            'BZ',
        ];
    }

    protected function getUrl()
    {
        return $this->url;
    }

    protected function getHttpClient()
    {
        if (!is_null($this->httpClient)) {
            return $this->httpClient;
        }

        $stack = GuzzleHttp\HandlerStack::create();

        if ($this->logger instanceof LoggerInterface) {
            $stack->push(GuzzleHttp\Middleware::log(
                $this->logger,
                new GuzzleHttp\MessageFormatter(GuzzleHttp\MessageFormatter::DEBUG)
            ));
        }

        $this->httpClient = new GuzzleHttp\Client([
            'base_uri' => $this->getUrl(),
            'handler'  => $stack,
        ]);

        return $this->httpClient;
    }

    /**
     * Returns checksum according Opteck Checksum Mechanism.
     *
     * @param $data
     *
     * @return string
     */
    protected function getChecksum($data)
    {
        $stringForHash = $this->partnerId.http_build_query($data);

        return strtoupper(hash('md5', $stringForHash));
    }

    protected function postRequest($url, $data)
    {
        try {
            $response = (string) $this->getHttpClient()
                ->post($url, [
                    'form_params' => $data,
                    'headers'     => [
                        'User-Agent'   => 'Opteck API Client',
                    ],
                ])->getBody();

            return $response;
        } catch (GuzzleHttp\Exception\ClientException $e) {
            throw new \Exception($e->getResponse()->getReasonPhrase(), $e->getResponse()->getStatusCode());
        }
    }

    /**
     * @param string $assetName Asset name, e.g.: 'EURUSD'.
     *
     * @throws \Opteck\Exceptions\AssetNotFoundException
     *
     * @return int
     */
    public function resolveAssetNameToId($assetName)
    {
        $assets = $this->getAssets();

        foreach ($assets as $asset) {
            $clearAssetName = str_replace('/', '', $asset->getName());
            if (strcasecmp($clearAssetName, $assetName) === 0) {
                return $asset->getId();
            }
        }

        throw new AssetNotFoundException(null, 'Asset ['.$assetName.'] not found');
    }
}
