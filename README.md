# Api Client

Библиотека предоставляющая удобный интерфейс для доступа к API интерфейсу [rabota.ru](http://rabota.ru/).
Подробней об использовании API и доступных методах читайте в [документации](http://dev.rabota.ru/docs/).

## Установка

TODO Библиотека ставится через composer:

```
composer require https://git.rabota.space/rdw/rabota-api-client
```

## Использование

Пример авторизации приложения по средствам библиотеки:
```php

use RabotaApi\Client;
use RabotaApi\Exception;

session_start();

$client = new Client(
    APP_ID, // код приложения
    APP_SECRET, // секретный ключ приложения
    $_SESSION['token'],
    $_SESSION['expires']
);

if (!empty($_GET['code'])) {
    $client->getAccessTokenFromCode($_GET['code']);
    header('Location: http://'.$_SERVER['HTTP_HOST'], true, 301);
    exit;
}

if (!$client->getAccessToken()) {
    header('Location: '.$client->getAuthenticationUrl('http://'.$_SERVER['HTTP_HOST']), true, 301);
    exit;
}

try {
    $response = $client->fetch('/v4/users/get.json', ['ids' => ['me'], 'fields' => 'id,name'] );
    echo '<pre>';
    print_r($response->getJsonDecode());
    echo '</pre>';
} catch (Exception $e) {
    echo "Ошибка: {$e->getMessage()}";
}
```

