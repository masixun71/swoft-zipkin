![](https://img.shields.io/badge/version-v0.0.0.1-red.svg)
![](https://img.shields.io/badge/php-%3E=7.1-orange.svg)
![](https://img.shields.io/badge/swoole-%3E=4.0-blue.svg)


# 简介
本项目属于swoft的zipkin client,非侵入式地对项目环境进行跟踪并且异步上报到zipkin server,可以和其他的swoft项目或者其他语言（java，go）进行全链路监控。




# 配置步骤

## 1.composer
```php
        "jcchavezs/zipkin-opentracing": "^0.1.2",
        "opentracing/opentracing": "1.0.0-beta5",
        "extraswoft/zipkin": "*"
```

因为opentracing/opentracing的最新版本是一个dev版本，所以外部项目comoposer引入是会报错的，所以需要显示的把配置放入**composer.json**,然后 *composer update*。

## 2.config/properties/app.php 添加

#### 需要在app文件，beanScan里加上扫描我们的命名空间
```php
      'beanScan' => [
        "ExtraSwoft\\Zipkin\\"
    ],
```



## 3.config/beans/base.php添加我们的中间件
```php
    'serverDispatcher' => [
        'middlewares' => [
            \Swoft\View\Middleware\ViewMiddleware::class,
            ZipkinMiddleware::class
//             \Swoft\Devtool\Middleware\DevToolMiddleware::class,
            // \Swoft\Session\Middleware\SessionMiddleware::class,
        ]
    ],
```

## 4.在.env配置文件中添加以下配置
##### ZIPKIN_HOST:  zipkin server 的地址

##### ZIPKIN_RAND:  采样率，100为100%



```php
  #Zipkin
ZIPKIN_HOST=http://0.0.0.0:9411
ZIPKIN_RAND=100
```

## 5.httpClient 的修改
当我们使用swoft官方的httpClient的时候，需要使用我们客户端的adapter

```php
$client = new Client(['adapter' => new AddZipkinAdapter()]);
```

当然，你也可以看下我们适配器的源码放到自己的适配器里，比较简单




# 源码修改

因为在mysql，redis和http的请求上没有钩子函数，所以我们需要自己实现，只要在请求开始和结束加上事件触发即可。建议自己或者公司项目直接fork官方的[swoft-component](https://github.com/swoft-cloud/swoft-component),然后根据自己需要开发，并且隔一段时间同步最新代码，在swoft里面composer使用component这个仓库。



## 1.mysql（协程）

### src/db/src/Db.php中，在$connection->prepare($sql);前添加(注意命名空间加入)
```php

         Log::profileStart($profileKey);
+        App::trigger('Mysql', 'start', $profileKey, $sql);
         $connection->prepare($sql);
         $params = self::transferParams($params);
         $result = $connection->execute($params);
```
### src/db/src/DbCoResult.php中，在Log::profileEnd($this->profileKey);后添加(注意命名空间加入)
```php
         $this->release();

         Log::profileEnd($this->profileKey);
+        App::trigger('Mysql', 'end', $this->profileKey);
         return $result;
```


## 2.redis (非协程),协程可以根据自己需要添加
### src/redis/src/Redis.php(注意命名空间加入)

### 在 $result = $connection->$method(...$params);前后添加

```php
         $connectPool = App::getPool($this->poolName);
         /* @var ConnectionInterface $client */
         $connection = $connectPool->getConnection();
+        App::trigger('Redis', 'start', $method, $params);
         $result     = $connection->$method(...$params);
         $connection->release(true);
+        App::trigger('Redis', 'end');

         return $result;
```
## 3.httpClient (协程)
### src/http-client/src/Adapter/CoroutineAdapter.php

### 在 $client->execute($path);前添加

```php
 if ($query !== '') $path .= '?' . $query;

         $client->setDefer();
+        App::trigger('HttpClient', 'start', $request, $options);
         $client->execute($path);

         App::profileEnd($profileKey);
```
### src/http-client/src/HttpCoResult.php
### 在 $client->close();后添加

```php
         $this->recv();
         $result = $client->body;
         $client->close();
+        App::trigger('HttpClient', 'end');
         $headers = value(function () {
             $headers = [];
```



# 完成
## 完成以上修改后，重新composer引入新的包，然后重启项目就可以了


# 使用zipkin server
```php
   docker run -d -p 9411:9411 openzipkin/zipkin
```



# 效果图
每个swoft项目通过这些步骤后都可以进行监控了，下面是两个swoft采用之后的全链路效果图
![zipkin httpClient](https://upload-images.jianshu.io/upload_images/12890383-88568d9d8cc02d15.png?imageMogr2/auto-orient/strip%7CimageView2/2/w/1240)

![zipkin httpClient2](https://upload-images.jianshu.io/upload_images/12890383-300269266dcf94bb.png?imageMogr2/auto-orient/strip%7CimageView2/2/w/1240)


# 后记
####如果你想对全链路有更深的了解或者对我的项目实现有所了解，甚至想应用到其他php框架或者其他语言上去，可以看下我写的这篇文章[php全链路监控完全实现（swoft举例）](https://www.jianshu.com/p/7aace43ea2a1)