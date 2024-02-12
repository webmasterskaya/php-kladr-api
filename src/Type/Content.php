<?php

namespace Webmasterskaya\Kladr\Type;

/**
 * Допустимые типы полей адреса
 */
class Content
{
    /**
     * Регион
     */
    public const REGION = 'region';

    /**
     * Район
     */
    public const DISTRICT = 'district';

    /**
     * Населённый пункт
     */
    public const CITY = 'city';

    /**
     * Улица
     */
    public const STREET = 'street';

    /**
     * Дом
     */
    public const BUILDING = 'building';
}