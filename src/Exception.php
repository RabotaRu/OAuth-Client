<?php
/**
 * API клиент для сайта Rabota.RU
 *
 * @license https://spdx.org/licenses/0BSD.html BSD Zero Clause License
 */

namespace RabotaApi;

/**
 * Исключение API клиента
 */
class Exception extends \Exception
{
    /**
     * Ошибка
     *
     * @var string
     */
    private $error;

    /**
     * Описание ошибки
     *
     * @var string
     */
    private $description;

    /**
     * Диалог
     *
     * @var \RabotaApi\Response
     */
    private $response;

    /**
     * Конструктор
     *
     * @param string              $error       Ошибка
     * @param string              $description Описание ошибки
     * @param \RabotaApi\Response $response    Диалог
     */
    public function __construct($error, $description, Response $response = null)
    {
        $this->error       = $error;
        $this->response    = $response;
        $this->description = $description;
        parent::__construct($error, $response ? $response->getHttpCode() : 500);
    }

    /**
     * Возвращает ошибку
     *
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Возвращает описание ошибки
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Возвращает диалог
     *
     * @return string
     */
    public function getResponse()
    {
        return $this->response;
    }
}