<?php
namespace ExtraSwoft\Zipkin\Middlewares;

use ExtraSwoft\Zipkin\Manager\TracerManager;
use const OpenTracing\Formats\TEXT_MAP;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Swoft\App;
use Swoft\Bean\Annotation\Bean;
use Swoft\Core\RequestContext;
use Swoft\Http\Message\Middleware\MiddlewareInterface;
use Swoft\Http\Message\Uri\Uri;
use OpenTracing\GlobalTracer;
use ZipkinOpenTracing\SpanContext;


/**
 * @Bean()
 */
class ZipkinMiddleware implements MiddlewareInterface
{

    /**
     * Process an incoming server request and return a response, optionally delegating
     * response creation to a handler.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \InvalidArgumentException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $spanContext = GlobalTracer::get()->extract(
            TEXT_MAP,
            RequestContext::getRequest()->getSwooleRequest()->header
        );

        if ($spanContext instanceof SpanContext)
        {
            $span = GlobalTracer::get()->startSpan('server', ['child_of' => $spanContext]);
        }
        else
        {
            $rand = env('ZIPKIN_RAND');
            if (rand(0,100) > $rand)
            {
                return $handler->handle($request);
            }

            $span = GlobalTracer::get()->startSpan('server');
        }
        \Swoft::getBean(TracerManager::class)->setServerSpan($span);


        $response = $handler->handle($request);

        GlobalTracer::get()->inject($span->getContext(), TEXT_MAP,
            RequestContext::getRequest()->getSwooleRequest()->header);

        $span->finish();
        GlobalTracer::get()->flush();

        return $response;
    }
}