<?php

declare(strict_types=1);

namespace BitBag\SyliusImojePlugin\Client;

use BitBag\SyliusImojePlugin\Exception\ImojeBadRequestException;
use BitBag\SyliusImojePlugin\Model\PaymentMethod\ServiceModel;
use BitBag\SyliusImojePlugin\Model\PaymentMethod\ServiceModelInterface;
use BitBag\SyliusImojePlugin\Model\TransactionModelInterface;
use BitBag\SyliusImojePlugin\Provider\RequestParams\RequestParamsProviderInterface;
use Http\Message\MessageFactory;
use Nyholm\Psr7\Stream;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use GuzzleHttp\ClientInterface as DeprecatedClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Serializer;

final class ImojeApiClient implements ImojeApiClientInterface
{
    public function __construct(
        private RequestParamsProviderInterface $requestParamsProvider,
        private Serializer $serializer,
        private MessageFactory|RequestFactoryInterface $requestFactory,
        private ClientInterface|DeprecatedClientInterface $httpClient,
        private string $token,
        private string $url,
        private ?StreamFactoryInterface $streamFactory = null,
    ) {
    }

    public function createTransaction(
        TransactionModelInterface $transactionModel
    ): ResponseInterface {
        $url = $this->url . self::TRANSACTION_ENDPOINT;
        $parameters = $this->requestParamsProvider->buildRequestParams($transactionModel, $this->token);

        return $this->sendRequest(Request::METHOD_POST, $url, $parameters);
    }

    public function getShopInfo(string $serviceId): ServiceModelInterface
    {
        $url = $this->url . 'service/' . $serviceId;
        $parameters = $this->requestParamsProvider->buildAuthorizeRequest($this->token);

        $response = $this->sendRequest(Request::METHOD_GET, $url, $parameters);

        $json = \json_decode($response->getBody()->getContents(), true);
        $servicePayload = $json['service'] ?? [];
        $result = $this->serializer->denormalize($servicePayload, ServiceModel::class, 'json');

        return $result;
    }

    public function getTransactionData(string $url): ResponseInterface
    {
        $parameters = $this->requestParamsProvider->buildAuthorizeRequest($this->token);

        return $this->sendRequest(Request::METHOD_GET, $url, $parameters);
    }

    public function refundTransaction(
        string $url,
        string $serviceId,
        int $amount
    ): ResponseInterface {
        $parameters = $this->requestParamsProvider->buildRequestRefundParams($this->token, $serviceId, $amount);

        return $this->sendRequest(Request::METHOD_POST, $url, $parameters);
    }

    private function sendRequest(string $method, string $url, array $parameters = []): ResponseInterface
    {
        $request = $this->requestFactory->createRequest($method, $url);

        if (true === isset($parameters['body'])) {
            $request = $request->withBody(
                null === $this->streamFactory
                    ? Stream::create($parameters['body'])
                    : $this->streamFactory->createStream($parameters['body']),
            );
        }

        if (true === isset($parameters['headers'])) {
            foreach ($parameters['headers'] as $key => $value) {
                $request = $request->withHeader($key, $value);
            }
        }

        try {
            if ($this->httpClient instanceof DeprecatedClientInterface) {
                $response = $this->httpClient->send($request);
            } else {
                $response = $this->httpClient->sendRequest($request);
            }
        } catch (ClientExceptionInterface|GuzzleException $e) {
            throw new ImojeBadRequestException($e->getMessage());
        }

        return $response;
    }
}
