<?php

namespace ExtraSwoft\Zipkin\Factory;

use RuntimeException;
use Swoft\HttpClient\Client;
use Zipkin\Reporters\Http\ClientFactory;

final class SwoftHttpFactory implements ClientFactory
{
    /**
     * @throws \BadFunctionCallException if the curl extension is not installed.
     */
    public static function create()
    {
        return new self();
    }

    /**
     * {@inheritdoc}
     */
    public function build(array $options = [])
    {
        /**
         * @param string $payload
         * @throws RuntimeException
         * @return void
         */
        return function ($payload) use ($options) {

            $client = new Client([
                'base_uri' => env('ZIPKIN_HOST'),
                'timeout' => 3,
            ]);

            swoole_timer_after(1000, function () use ($options, $client, $payload) {
                $result = $client->post($options['endpoint_url'], [
                    'json' => json_decode($payload)
                ])->getResponse();

                if ($result->getStatusCode() !== 202) {
                    logger()->warning(
                        sprintf('Reporting of spans failed, status code %d', $result->getStatusCode())
                    );
                }
            });

        };
    }
}
