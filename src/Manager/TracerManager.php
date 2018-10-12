<?php
declare(strict_types=1);

namespace ExtraSwoft\Zipkin\Manager;

use const OpenTracing\Formats\TEXT_MAP;
use OpenTracing\GlobalTracer;
use Swoft\Bean\Annotation\Bean;
use Swoft\Bean\Annotation\Value;
use Swoft\Core\Coroutine;
use Zipkin\Endpoint;
use Zipkin\Reporters\Http;
use Zipkin\Samplers\BinarySampler;
use Zipkin\TracingBuilder;
use ZipkinOpenTracing\Tracer;
use ExtraSwoft\Zipkin\Factory\SwoftHttpFactory;

/**
 * @Bean()
 * Class TracerManager
 */
class TracerManager
{

    protected $serverSpans = [];

    /**
     * @Value("${config.zipkin.endpointUrl}")
     * @var string
     */
    public $endpointUrl = '/api/v2/spans';

    public function init()
    {
        $endpoint = Endpoint::create(env('PNAME'), $this->getIp(), null, env('TCPABLE') ? env('TCP_PORT') : env('HTTP_PORT'));
        $clientFactory = SwoftHttpFactory::create();
        $reporter = new Http($clientFactory, ['endpoint_url' => $this->endpointUrl]);
        $sampler = BinarySampler::createAsAlwaysSample();
        $tracing = TracingBuilder::create()
            ->havingLocalEndpoint($endpoint)
            ->havingSampler($sampler)
            ->havingReporter($reporter)
            ->build();

        $zipkinTrcer = new Tracer($tracing);

        GlobalTracer::set($zipkinTrcer); // optional

    }


    public function setServerSpan($span)
    {
        $cid = Coroutine::tid();

        $this->serverSpans[$cid] = $span;
    }

    public function getServerSpan()
    {
        $cid = Coroutine::tid();

        if (!isset($this->serverSpans[$cid]))
        {
            return null;
        }

        return $this->serverSpans[$cid];
    }


    public function getHeader()
    {
        $headers = [];
        $cid = Coroutine::tid();
        GlobalTracer::get()->inject($this->serverSpans[$cid]->getContext(), TEXT_MAP,
            $headers);

        return $headers;
    }


    private function getIp()
    {
        $result = shell_exec("/sbin/ifconfig");
        if (preg_match_all("/inet (\d+\.\d+\.\d+\.\d+)/", $result, $match) !== 0)  // 这里根据你机器的具体情况， 可能要对“inet ”进行调整， 如“addr:”，看如下注释掉的if
        {
            foreach ($match [0] as $k => $v) {
                if ($match [1] [$k] != "127.0.0.1") {
                    return $match[1][$k];
                }
            }
        }
        return '127.0.0.1';
    }

}