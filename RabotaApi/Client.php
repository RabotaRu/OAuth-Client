<?php
/**
 * API клиент для сайта Rabota.RU
 *
 * @author    Valentin Gernovich <vag@rdw.ru>
 * @license   http://www.opensource.org/licenses/bsd-license.php BSD License
 */

namespace RabotaApi;

/**
 * Api клиент
 */
class Client
{
    /**
     * HTTP методы
     *
     * @var string
     */
    public const
        HTTP_GET    = 'GET',
        HTTP_POST   = 'POST';

    /**
     * Хост для апи
     *
     * TODO add sandbox url
     *
     * В качестве песочницы может выступать демо домен: neptune.rabota.space
     * Он может содержать бейсик авторизация worker:umeC4Phahg
     *
     * @var string
     */
    private const HOST = 'http://api.dev.rabota.space';
    //private const HOST = 'https://api.rabota.ru';
    //private const HOST = 'https://neptune.rabota.space';

    /**
     * Основные эндпоинты
     *
     * @var string
     */
    private const
        POINT_AUTHORIZATION = '/oauth/authorize.html',    // Эндпоинт авторизации
        POINT_GET_TOKEN     = '/oauth/getToken.json',     // Эндпоинт получение токена по коду
        POINT_REFRESH_TOKEN = '/oauth/refreshToken.json', // Эндпоинт обновление токена
        POINT_LOGOUT        = '/oauth/logout.json';       // Эндпоинт завершение сеанса

    /**
     * Наименования полей
     *
     * @var string
     */
    private const
        FIELD_TOKEN     = 'token',     // Имя ключа токена
        FIELD_EXPIRES   = 'expires',   // Имя ключа времени жизни токена
        FIELD_SIGNATURE = 'signature', // Имя ключа подписи запроса
        FIELD_APP_ID    = 'app_id',    // Код приложения
        FIELD_REDIRECT  = 'redirect',  // Адрес возрата
        FIELD_DISPLAY   = 'display',   // Вид страницы авторизации
        FIELD_CODE      = 'code',      // Код для получения токена
        FIELD_TIME      = 'time';      // Код для получения токена

    /**
     * Вид отображения окна авторизации
     *
     * @var string
     */
    public const
        DISPLAY_PAGE  = 'page',  // в виде страници
        DISPLAY_POPUP = 'popup'; // в виде PopUp страници

    /**
     * Индификатор приложения
     *
     * @var string
     */
    protected $app_id = null;

    /**
     * Секретный код приложения
     *
     * @var string
     */
    protected $secret = null;

    /**
     * Token доступа
     *
     * @var string
     */
    protected $token = null;

    /**
     * Время устаревания токена
     *
     * @var integer
     */
    protected $expires = null;

    /**
     * Конструктор
     *
     * @param string|null $app_id  Индификатор приложения
     * @param string|null $secret  Секретный код приложения
     * @param null        $token
     * @param null        $expires
     *
     * @throws \Exception
     */
    public function __construct($app_id, $secret, &$token = null, &$expires = null)
    {
        if (!extension_loaded('curl')) {
            throw new \Exception('Нет расширения curl');
        }
        $this->app_id  = $app_id;
        $this->secret  = $secret;
        $this->token   = &$token;
        $this->expires = &$expires;
    }

    /**
     * Получение ссылки на автаризацию
     *
     * @param string $redirect Адрес редиректа после авторизации
     * @param string $display  Внешний вид диалога
     *
     * @return string
     */
    public function getAuthenticationUrl($redirect, $display = self::DISPLAY_PAGE)
    {
        $parameters = [
            self::FIELD_APP_ID   => $this->app_id,
            self::FIELD_REDIRECT => $redirect,
            self::FIELD_DISPLAY  => $display,
        ];
        return self::HOST.self::POINT_AUTHORIZATION.'?'.http_build_query($parameters, null, '&');
    }

    /**
     * Получение токена доступа
     *
     * @param string $code Код авторизации
     *
     * @return array
     * @throws \RabotaApi\Exception
     */
    public function requestToken($code)
    {
        $result = $this->fetch(
            self::POINT_GET_TOKEN,
            [
                self::FIELD_CODE   => $code,
            ],
            self::HTTP_POST,
            true
        )->getJsonDecode();

        if (isset($result[self::FIELD_TOKEN])) {
            $this->setToken($result[self::FIELD_TOKEN]);
            $this->expires = time()+$result[self::FIELD_EXPIRES];
        }
        return $result;
    }
    /**
     * Проверить устарел ли токен доступа
     *
     * @return boolean
     */
    public function isExpires()
    {
        return $this->expires - time() < 0;
    }
    /**
     * Получение текущего токена доступа
     *
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }
    /**
     * Время устаревания токена
     *
     * @return string
     */
    public function getExpires()
    {
        return $this->expires;
    }
    /**
     * Установить токен доступа
     *
     * @param string $token Токен доступа
     */
    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * Выполнить запрос
     *
     * @param string       $resource_url Адрес API метода
     * @param array        $parameters   Параметры запроса
     * @param string|null  $method       HTTP метод запроса
     * @param boolean|null $subscribe    Подписать запорс
     *
     * @return \RabotaApi\Response
     * @throws \RabotaApi\Exception
     */
    public function fetch(
        $resource_url,
        array $parameters = [],
        $method = self::HTTP_GET,
        $subscribe = false
    ) {
        // если токен устарел, обновляем его
        if($this->getToken() && $this->isExpires()) {
            $this->refreshToken();
        }
        // подписываем запрос при необходимости
        if ($subscribe)
        {
            $parameters[self::FIELD_TIME]      = time();
            $parameters[self::FIELD_SIGNATURE] = $this->getSignature($resource_url, $parameters);
        }
        // добавление токена в параметры запроса
        if ($this->token)
        {
            $parameters[self::FIELD_TOKEN] = $this->token;
        }
        return $this->executeRequest(
            $resource_url,
            $parameters,
            $method
        );
    }

    /**
     * Выполнить запрос
     *
     * @param string       $url        Адрес API метода
     * @param mixed        $parameters Параметры запроса
     * @param string|null  $method     HTTP метод запроса
     *
     * @return \RabotaApi\Response
     * @throws \RabotaApi\Exception
     */
    private function executeRequest(
        $url,
        array $parameters = [],
        $method = self::HTTP_GET
    ) {
        $url = self::HOST.$url;
        // параметры из url передаются в список параметров
        if (strpos($url, '?') !== false) {
            list($url, $url_params) = explode('?', $url, 2);
            parse_str($url_params, $url_params);
            $parameters = $url_params+$parameters;
        }
        $curl_options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_URL => $url,
        ];
        switch($method) {
            case self::HTTP_GET:
                $url .= '?'.http_build_query($parameters);
                $curl_options[CURLOPT_URL] = $url;
                break;
            case self::HTTP_POST:
                $curl_options[CURLOPT_POST] = true;
                $curl_options[CURLOPT_POSTFIELDS] = http_build_query($parameters);
                break;
            default:
                throw new Exception('no_support_method', 'Неподдерживаемый метод запроса', null);
        }

        $ch = curl_init();
        curl_setopt_array($ch, $curl_options);
        $dialogue = new Response(curl_exec($ch), $ch, $url, $parameters);
        curl_close($ch);
        $json_decode = $dialogue->getJsonDecode();
        if ($dialogue->getHttpCode() != 200) {
            $code = $dialogue->getHttpCode();
            $desc = 'Неизвестная ошибка';
            if (isset($json_decode['error'], $json_decode['description'])) {
                $code = $json_decode['error'];
                $desc = $json_decode['description'];
                // токен устарел
                if ($code == 'invalid_token') {
                    $this->refreshToken();
                    $parameters[self::FIELD_TOKEN] = $this->getToken();
                    return $this->executeRequest($url, $parameters, $method);
                }
                // токен не найден
                if ($code == 'undefined_token') {
                    $this->token = null;
                    $this->expires = null;
                }
            } elseif (isset($json_decode['code'], $json_decode['error'])) {
                $code = $json_decode['code'];
                $desc = $json_decode['error'];
            }
            throw new Exception($code, $desc, $dialogue);
        }
        return $dialogue;
    }

    /**
     * Выход
     *
     * @throws \RabotaApi\Exception
     */
    public function logout()
    {
        $this->fetch(
            self::POINT_LOGOUT,
            [
                self::FIELD_TOKEN => $this->token
            ],
            self::HTTP_GET
        );
        $this->token = null;
        $this->expires = null;
    }

    /**
     * Обновление токена доступа
     *
     * @return array
     * @throws \RabotaApi\Exception
     */
    public function refreshToken()
    {
        $result = $this->executeRequest(
            self::POINT_REFRESH_TOKEN,
            [self::FIELD_TOKEN => $this->token],
            self::HTTP_GET
        )->getJsonDecode();
        if (isset($result[self::FIELD_TOKEN])) {
            $this->setToken($result[self::FIELD_TOKEN]);
            $this->expires = strtotime($result[self::FIELD_EXPIRES]);
        }
        return $result;
    }

    /**
     * Строит сигнатуру для ссылки с POST параметрами
     *
     * @param string $url  Ссылка
     * @param array  $post POST параметры
     *
     * @return string
     */
    private function getSignature($url, array $post = [])
    {
        $and = (strpos($url, '?') === false) ? '?' : '&';
        $parsed = parse_url($url.$and.http_build_query($post));
        // параметры запроса
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $parsed['query']);
        } else {
            $parsed['query'] = [];
        }
        $url_hash = '';
        if (!empty($parsed['query'])) {
            unset($parsed['query'][self::FIELD_TOKEN], $parsed['query'][self::FIELD_SIGNATURE]);
            ksort($parsed['query']);
            $url_hash .= implode('', array_keys($parsed['query']));
            // получение значения из многомерного массива параметров
            while (count($parsed['query'])) {
                $value = array_shift($parsed['query']);
                if (is_array($value)) {
                    foreach ($value as $k => $v) {
                        array_unshift($parsed['query'], $k, $v);
                    }
                } else {
                    $url_hash .= $value;
                }
            }
        }
        unset($parsed['query']);
        ksort($parsed);
        $url_hash .= implode('', array_values($parsed));
        // хэш url с секретным кодом приложения
        return md5(md5($url_hash).$this->secret);
    }

}