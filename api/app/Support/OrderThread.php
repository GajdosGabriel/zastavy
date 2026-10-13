<?php

namespace App\Support;

use App\Models\Order;
use Symfony\Component\Mime\Email;

/**
 * Jedno e-mailové vlákno na objednávku.
 *
 * Potvrdenie objednávky má pevné Message-ID odvodené z uuid; každý ďalší e-mail
 * k objednávke naň odkazuje a má predmet „Re: …“, takže ho poštový klient zaradí
 * do tej istej konverzácie. ID sa nikde neukladá — dá sa kedykoľvek vypočítať.
 */
class OrderThread
{
    public const FROM = 'obchod@zastavy-vlajky.sk';

    public const FROM_NAME = 'Gajdoš Gabriel – Reprezent';

    public static function subject(Order $order): string
    {
        return 'Objednávka | '.$order->billing->company;
    }

    public static function replySubject(Order $order): string
    {
        return 'Re: '.self::subject($order);
    }

    /** Admin kópie majú vlastný koreň: dve správy s rovnakým Message-ID by Gmail zlúčil. */
    public static function id(Order $order, bool $admin = false): string
    {
        return 'order-'.$order->uuid.($admin ? '-admin' : '').'@'.substr(strrchr(self::FROM, '@'), 1);
    }

    public static function root(Email $message, Order $order, bool $admin = false): void
    {
        $headers = $message->getHeaders();
        $headers->remove('Message-ID');
        $headers->addIdHeader('Message-ID', self::id($order, $admin));
    }

    public static function reply(Email $message, Order $order, bool $admin = false): void
    {
        $headers = $message->getHeaders();
        $headers->addIdHeader('In-Reply-To', self::id($order, $admin));
        $headers->addIdHeader('References', self::id($order, $admin));
    }
}
