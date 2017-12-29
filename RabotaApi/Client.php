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
        HTTP_POST   = 'POST',
        HTTP_PUT    = 'PUT',
        HTTP_DELETE = 'DELETE';

    /**
     * Хост для апи
     * TODO add sandbox url
     *
     * @var string
     */
    private const HOST = 'http://api.dev.rabota.space';
    //private const HOST = 'https://api.rabota.ru';

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
        FIELD_ACCESS_TOKEN  = 'access_token',  // Имя ключа токена
        FIELD_EXPIRES_IN    = 'expires_in',    // Имя ключа времени жизни токена
        FIELD_SIGNATURE     = 'signature',     // Имя ключа подписи запроса
        FIELD_RESPONSE_TYPE = 'response_type', // Тип ответа, код или токен
        FIELD_APP_CODE      = 'app_code',      // Код приложения
        FIELD_REDIRECT_URI  = 'redirect_uri',  // Адрес возрата
        FIELD_DISPLAY       = 'display',       // Вид страницы авторизации
        FIELD_APP_SECRET    = 'app_secret',    // Секрет приложения
        FIELD_CODE          = 'code';          // Код для получения токена

    /**
     * Вид отображения окна авторизации
     *
     * @var string
     */
    public const
        DISPLAY_PAGE  = 'page',  // в виде страници
        DISPLAY_POPUP = 'popup'; // в виде PopUp страници

    /**
     * Тип ответа
     *
     * @var string
     */
    private const
        RESPONSE_TYPE_TOKEN = 'token', // Токен
        RESPONSE_TYPE_CODE  = 'code';  // Код для получения токена по серкетному ключу

    /**
     * Режим отладки оп умолчанию
     *
     * @var boolean
     */
    const DEFAULT_DEBUG_MODE = false;

    /**
     * Индификатор приложения
     *
     * @var string
     */
    protected $app_code = null;

    /**
     * Секретный код приложения
     *
     * @var string
     */
    protected $app_secret = null;

    /**
     * Token доступа
     *
     * @var string
     */
    protected $access_token = null;

    /**
     * Время устаревания токена
     *
     * @var integer
     */
    protected $access_token_expires = null;

    /**
     * Режим отладки
     *
     * @var boolean
     */
    protected $debug_mode = self::DEFAULT_DEBUG_MODE;

    /**
     * Конструктор
     *
     * @param string|null $app_code             Индификатор приложения
     * @param string|null $app_secret           Секретный код приложения
     * @param null        $access_token
     * @param null        $access_token_expires
     *
     * @throws \Exception
     */
    public function __construct($app_code, $app_secret, &$access_token = null, &$access_token_expires = null)
    {
        if (!extension_loaded('curl')) {
            throw new \Exception('Нет расширения curl');
        }
        $this->app_code     = $app_code;
        $this->app_secret = $app_secret;
        $this->access_token = &$access_token;
        $this->access_token_expires = &$access_token_expires;
    }

    /**
     * Получение ссылки на автаризацию
     *
     * @param string $redirect_uri Адрес редиректа после авторизации
     * @param string $display      Внешний вид диалога
     * @return string
     */
    public function getAuthenticationUrl($redirect_uri, $display = self::DISPLAY_PAGE)
    {
        $parameters = [
            self::FIELD_RESPONSE_TYPE => self::RESPONSE_TYPE_CODE,
            self::FIELD_APP_CODE      => $this->app_code,
            self::FIELD_REDIRECT_URI  => $redirect_uri,
            self::FIELD_DISPLAY       => $display,
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
    public function getAccessTokenFromCode($code)
    {
        $result = $this->executeRequest(
            self::POINT_GET_TOKEN,
            [
                self::FIELD_RESPONSE_TYPE => self::RESPONSE_TYPE_TOKEN,
                self::FIELD_CODE          => $code,
                self::FIELD_APP_CODE      => $this->app_code,
                self::FIELD_APP_SECRET    => $this->app_secret,
            ],
            self::HTTP_POST,
            false
        )->getJsonDecode();

        if (isset($result[self::FIELD_ACCESS_TOKEN])) {
            $this->setAccessToken($result[self::FIELD_ACCESS_TOKEN]);
            $this->access_token_expires = time()+$result[self::FIELD_EXPIRES_IN];
        }
        return $result;
    }
    /**
     * Проверить устарел ли токен доступа
     *
     * @return boolean
     */
    public function isExpiresAccessToken()
    {
        return $this->access_token_expires - time() < 0;
    }
    /**
     * Получение текущего токена доступа
     *
     * @return string
     */
    public function getAccessToken()
    {
        return $this->access_token;
    }
    /**
     * Время устаревания токена
     *
     * @return string
     */
    public function getExpires()
    {
        return $this->access_token_expires;
    }
    /**
     * Установить токен доступа
     *
     * @param string $token Токен доступа
     */
    public function setAccessToken($token)
    {
        $this->access_token = $token;
    }

    /**
     * Выполнить запрос
     *
     * @param string       $resource_url Адрес API метода
     * @param array        $parameters   Параметры запроса
     * @param string|null  $method       HTTP метод запроса
     * @param boolean|null $subscribe    Подписать запорс
     * @param boolean|null $debug        Режим отладки
     *
     * @return \RabotaApi\Response
     * @throws \RabotaApi\Exception
     */
    public function fetch(
        $resource_url,
        array $parameters = [],
        $method = self::HTTP_GET,
        $subscribe = false,
        $debug = null
    ) {
        // если токен устарел, обновляем его
        if($this->getAccessToken() && $this->isExpiresAccessToken()) {
            $this->refreshAccessToken();
        }
        // подписываем запрос при необходимости
        if ($subscribe)
        {
            $parameters[self::FIELD_SIGNATURE] = $this->getSignature($resource_url, $parameters);
        }
        // добавление токена в параметры запроса
        if ($this->access_token)
        {
            $parameters[self::FIELD_ACCESS_TOKEN] = $this->access_token;
        }
        return $this->executeRequest(
            $resource_url,
            $parameters,
            $method,
            is_null($debug) ? $this->debug_mode : $debug
        );
    }

    /**
     * Выполнить запрос
     *
     * @param string       $url        Адрес API метода
     * @param mixed        $parameters Параметры запроса
     * @param string|null  $method     HTTP метод запроса
     * @param boolean|null $debug      Режим отладки
     *
     * @return \RabotaApi\Response
     * @throws \RabotaApi\Exception
     */
    private function executeRequest(
        $url,
        array $parameters = [],
        $method = self::HTTP_GET,
        $debug = self::DEFAULT_DEBUG_MODE
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
        // в режиме отладки сохраняем заголовки
        if ($debug) {
            $curl_options[CURLINFO_HEADER_OUT] = true;
            $curl_options[CURLOPT_HEADER] = true;
        }
        switch($method) {
            case self::HTTP_GET:
                $url .= '?'.http_build_query($parameters);
                $curl_options[CURLOPT_URL] = $url;
                break;
            case self::HTTP_POST:
                $curl_options[CURLOPT_POST] = true;
                $curl_options[CURLOPT_POSTFIELDS] = http_build_query($parameters);
                break;
            case self::HTTP_PUT:
            case self::HTTP_DELETE:
                $curl_options[CURLOPT_POSTFIELDS] = http_build_query($parameters);
                break;
            default:
                throw new Exception('no_support_method', 'Неподдерживаемый метод запроса', null);
        }

        $ch = curl_init();
        curl_setopt_array($ch, $curl_options);
        $dialogue = new Response(curl_exec($ch), $ch, $url, $parameters, $debug);
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
                    $this->refreshAccessToken();
                    $parameters[self::FIELD_ACCESS_TOKEN] = $this->getAccessToken();
                    return $this->executeRequest($url, $parameters, $method, $debug);
                }
                // токен не найден
                if ($code == 'undefined_token') {
                    $this->access_token = null;
                    $this->access_token_expires = null;
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
            [self::FIELD_ACCESS_TOKEN => $this->access_token],
            self::HTTP_GET
        );
        $this->access_token = null;
        $this->access_token_expires = null;
    }

    /**
     * Обновление токена доступа
     *
     * @return array
     * @throws \RabotaApi\Exception
     */
    public function refreshAccessToken()
    {
        $result = $this->executeRequest(
            self::POINT_REFRESH_TOKEN,
            [self::FIELD_ACCESS_TOKEN => $this->access_token],
            self::HTTP_GET,
            false
        )->getJsonDecode();
        if (isset($result[self::FIELD_ACCESS_TOKEN])) {
            $this->setAccessToken($result[self::FIELD_ACCESS_TOKEN]);
            $this->access_token_expires = strtotime($result[self::FIELD_EXPIRES_IN]);
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
            unset($parsed['query'][self::FIELD_ACCESS_TOKEN], $parsed['query'][self::FIELD_SIGNATURE]);
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
        return md5(md5($url_hash).$this->app_secret);
    }
    
    /**
     * Устанавливает режим отладки
     *
     * @param boolean $mode Режим отладки
     *
     * @return \RabotaApi\Client
     */
    public function setDebugMode($mode)
    {
        $this->debug_mode = (bool)$mode;
        return $this;
    }
}