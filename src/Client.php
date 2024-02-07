<?php

namespace Webmasterskaya\Kladr;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use PsrDiscovery\Discover;
use PsrDiscovery\Exceptions\SupportPackageNotFoundException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmasterskaya\Kladr\Exception\RuntimeException;

class Client
{
    /**
     * API токен доступ к сервису
     *
     * @var string
     */
    protected mixed $token;

    /**
     * Глобальные параметры клиента
     *
     * @var array<string,mixed>
     */
    protected array $config
        = [
            'url' => 'https://kladr-api.com/api.php',
        ];

    /**
     * @param   string|null  $token   API токен доступ к сервису. Если не указан, то подключение осуществляется к бесплатной версии сервиса
     * @param   array        $config  Массив глобальных настроек клиента
     */
    public function __construct(?string $token, array $config = [])
    {
        // Если токен отсутствует, то меняем URL сервера на бесплатный
        if (!empty($token)) {
            $this->token = $token;
        } else {
            $this->config['url'] = 'https://kladr-api.ru/api.php';
        }

        if (!empty($config)) {
            $this->config = array_replace_recursive($this->config, $config);
        }
    }

    /**
     * Производит поиск по всем доступным полям адреса
     *
     * @param   string  $query   Строка запроса (что хотите найти?)
     * @param   array   $config  Параметры запроса. Подробнее о параметрах смотри в README.md
     * @param   int     $limit   Количество результатов в ответе
     * @param   int     $offset  Смещение результатов (для организации постраничной навигации)
     *
     * @return array
     */
    public function queryString(string $query, array $config = [], int $limit = 10, int $offset = 0): array
    {
        /** @var \Symfony\Component\OptionsResolver\OptionsResolver $resolver */
        static $resolver;

        if (!isset($resolver)) {
            $resolver = new OptionsResolver();

            $resolver
                ->setDefined(['withParent', 'regionId', 'districtId', 'cityId', 'contentType'])
                ->setAllowedTypes('regionId', ['string'])
                ->setAllowedTypes('districtId', ['string'])
                ->setAllowedTypes('cityId', ['string'])
                ->setAllowedTypes('withParent', ['int', 'bool'])
                ->setIgnoreUndefined();
        }

        $config = $resolver->resolve($config);

        $config['oneString'] = 1;

        return $this->execute($query, $config, $limit, $offset);
    }

    /**
     * Производит поиск только по указанному полю адреса
     *
     * @param   string  $query   Строка запроса (что хотите найти?)
     * @param   array   $config  Параметры запроса. Подробнее о параметрах смотри в README.md
     * @param   int     $limit   Количество результатов в ответе
     * @param   int     $offset  Смещение результатов (для организации постраничной навигации)
     *
     * @return array
     */
    public function queryField(string $query, array $config = [], int $limit = 10, int $offset = 0): array
    {
        /** @var \Symfony\Component\OptionsResolver\OptionsResolver $resolver */
        static $resolver;

        if (!isset($resolver)) {
            $resolver = new OptionsResolver();

            $resolver
                ->setDefined(['withParent', 'regionId', 'districtId', 'cityId', 'streetId', 'buildingId', 'contentType', 'typeCode'])
                ->setAllowedTypes('regionId', ['string'])
                ->setAllowedTypes('districtId', ['string'])
                ->setAllowedTypes('cityId', ['string'])
                ->setAllowedTypes('streetId', ['string'])
                ->setAllowedTypes('buildingId', ['string'])
                ->setAllowedTypes('zip', ['int', 'string'])
                ->setAllowedTypes('withParent', ['int', 'bool'])
                ->setAllowedValues('contentType', [
                    Type\Content::REGION,
                    Type\Content::DISTRICT,
                    Type\Content::CITY,
                    Type\Content::STREET,
                    Type\Content::BUILDING
                ])
                ->setAllowedValues('typeCode', [
                    Type\Code::CITY,
                    Type\Code::VILLAGE,
                    Type\Code::RURAL,
                    Type\Code::CITY | Type\Code::VILLAGE,
                    Type\Code::CITY | Type\Code::RURAL,
                    Type\Code::VILLAGE | Type\Code::RURAL,
                    Type\Code::CITY | Type\Code::VILLAGE | Type\Code::RURAL,
                ])
                ->setRequired(['contentType'])
                ->setIgnoreUndefined();

            // Почтовый индекс работает только при contentType = building
            if ($config['contentType'] = Type\Content::BUILDING) {
                $resolver->setDefined(['zip']);
            }
        }

        $config = $resolver->resolve($config);

        return $this->execute($query, $config, $limit, $offset);
    }

    /**
     * Формирует запрос к API
     *
     * @param   string  $query   Строка запроса (что хотите найти?)
     * @param   array   $config  Параметры запроса. Подробнее о параметрах смотри в README.md
     * @param   int     $limit   Количество результатов в ответе
     * @param   int     $offset  Смещение результатов (для организации постраничной навигации)
     *
     * @return array
     */
    protected function execute(string $query, array $config, int $limit = 10, int $offset = 0): array
    {
        /**
         * @var \Psr\Http\Client\ClientInterface          $client
         * @var \Psr\Http\Message\RequestFactoryInterface $request_factory
         */
        static $client, $request_factory;

        if (!isset($client)) {
            try {
                $client = Discover::httpClient();
                if (!($client instanceof ClientInterface)) {
                    throw new RuntimeException('PSR-18 HTTP Client not found');
                }
            } catch (SupportPackageNotFoundException $e) {
                throw new RuntimeException('PSR-18 HTTP Client not found');
            }
        }

        if (!isset($request_factory)) {
            try {
                $request_factory = Discover::httpRequestFactory();
                if (!($request_factory instanceof RequestFactoryInterface)) {
                    throw new RuntimeException('PSR-17 HTTP Request Factory not found');
                }
            } catch (SupportPackageNotFoundException $e) {
                throw new RuntimeException('PSR-17 HTTP Request Factory not found');
            }
        }

        $config['query'] = trim($query);

        if ($limit > 0) {
            $config['limit'] = $limit;

            if ($offset > 0) {
                $config['offset'] = $offset;
            }
        }

        if (array_key_exists('withParent', $config)) {
            $config['withParent'] = (int)$config['withParent'];
        }

        $url = $this->buildUrl($config);

        $request = $request_factory
            ->createRequest('GET', $url);

        try {
            $response = $client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new RuntimeException('HTTP Client error: ' . $e->getMessage());
        }

        $status = $response->getStatusCode();
        if ($status >= 400 && $status < 500) {
            throw new RuntimeException('HTTP Client error: ' . $response->getReasonPhrase());
        }

        if ($status >= 500) {
            throw new RuntimeException('HTTP Server error: ' . $response->getReasonPhrase());
        }

        $result = $response->getBody()->__toString();

        try {
            $result = json_decode($result, true, 512, JSON_BIGINT_AS_STRING | JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('API Client error: error on parse response');
        }

        return $result;
    }

    /**
     * Формирует полный URL запроса к API, на основе переданных параметров
     *
     * @param   array  $config  Параметры запроса. Подробнее о параметрах смотри в README.md
     *
     * @return string
     */
    protected function buildUrl(array $config): string
    {
        if (isset($this->token)) {
            $config['token'] = $this->token;
        } else {
            unset($config['token']);
        }

        $query = http_build_query($config, '', '&', PHP_QUERY_RFC3986);

        if (str_contains($this->config['url'], '?')) {
            $url = $this->config['url'] . '&' . $query;
        } else {
            $url = $this->config['url'] . '?' . $query;
        }

        return $url;
    }
}