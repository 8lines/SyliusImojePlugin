<?php

declare(strict_types=1);

namespace BitBag\SyliusImojePlugin\Provider;

use BitBag\SyliusImojePlugin\Client\ImojeApiClient;
use BitBag\SyliusImojePlugin\Factory\Serializer\SerializerFactoryInterface;
use BitBag\SyliusImojePlugin\Provider\RequestParams\RequestParamsProviderInterface;
use GuzzleHttp\ClientInterface as DeprecatedClientInterface;
use Http\Message\MessageFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class ImojeClientProvider implements ImojeClientProviderInterface
{
    public function __construct(
        private ImojeClientConfigurationProviderInterface $imojeClientConfigurationProvider,
        private RequestParamsProviderInterface $requestParamsProvider,
        private SerializerFactoryInterface $serializerFactory,
        private MessageFactory|RequestFactoryInterface $requestFactory,
        private ClientInterface|DeprecatedClientInterface $httpClient,
        private ?StreamFactoryInterface $streamFactory = null,
    ) {
    }

    public function getClient(string $code): ImojeApiClient
    {
        $configuration = $this->imojeClientConfigurationProvider->getPaymentMethodConfiguration($code);
        $token = $configuration->getToken();
        $merchantId = $configuration->getMerchantId();
        $url = $configuration->isProd() ? $configuration->getProdUrl() : $configuration->getSandboxUrl();

        $completeUrl = \sprintf('%s/%s/', $url, $merchantId);

        return new ImojeApiClient(
            $this->requestParamsProvider,
            $this->serializerFactory->createSerializerWithNormalizer(),
            $this->requestFactory,
            $this->httpClient,
            $token,
            $completeUrl,
            $this->streamFactory,
        );
    }
}
