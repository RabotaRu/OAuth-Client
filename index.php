<?php
/**
 * API клиент для сайта Rabota.RU
 *
 * @license https://spdx.org/licenses/0BSD.html BSD Zero Clause License
 */

include_once 'src/Client.php';
include_once 'src/Response.php';
include_once 'src/Exception.php';

use RabotaApi\Client;
use RabotaApi\Exception;

session_start();

$config = include "config.php";

// Создаем API клиента
$client = new Client(
    $config['app_id'],$config['secret'], $_SESSION['token'], $_SESSION['expires']
);


// Если редирект с авторизации приложения с токеном

if (isset($_GET['code'])) {
    try {
        $client->requestToken($_GET['code']);
        $_SESSION['token'] = $client->getToken();
        $_SESSION['expires'] = $client->getExpires();
    } catch (Exception $e) {
        echo "Ошибка: {$e->getMessage()}";
    }

    // Редиректим на себя же, чтоб убрать код из GET параметра
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header('Location: http://'.$_SERVER['HTTP_HOST'], true, 301);
    exit;
}

// Неавторизирован
if (!$client->getToken() && !isset($_GET['auth'])) {
    echo '<a href="?auth">Вход</a>';
    exit;
}

// Авторизация приложения
if (!$client->getToken() && isset($_GET['auth'])) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header('Location: '.$client->getAuthenticationUrl('http://'.$_SERVER['HTTP_HOST']), true, 301);
    exit;
}

// Авторизированное состояние
try {
    $response = $client->fetch(
        $config['api']['route'], $config['api']['params'], "POST"
    );
    $_SESSION['token'] = $client->getToken();
    $_SESSION['expires'] = $client->getExpires();
    echo '<pre>';
    print_r($response->getJsonDecode());
    echo '</pre>';
} catch (Exception $e) {
    echo "Ошибка: {$e->getMessage()}";
}


