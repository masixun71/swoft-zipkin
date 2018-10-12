<?php
declare(strict_types=1);

namespace App\Lib\HttpClient;

use ExtraSwoft\Zipkin\Manager\TracerManager;
use Psr\Http\Message\RequestInterface;
use Swoft\Core\RequestContext;
use Swoft\HttpClient\Adapter\CoroutineAdapter;
use Swoft\HttpClient\HttpResultInterface;

class AddZipkinAdapter extends CoroutineAdapter
{
    public function request(RequestInterface $request, array $options = []): HttpResultInterface
    {
        $options['_headers'] = array_merge($options['_headers'], \Swoft::getBean(TracerManager::class)->getHeader());

        return parent::request($request, $options);
    }

}

