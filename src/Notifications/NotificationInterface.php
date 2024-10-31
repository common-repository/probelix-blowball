<?php
namespace PbxBlowball\Notifications;

if (! defined ( 'ABSPATH' )) {
	exit (); // Exit if accessed directly
}

/**
 * Interface for representing wordpress notification
 */
interface NotificationInterface
{
    public function setId(string $value):void;

    public function setType(string $value):void;

    public function setTitle(string $value):void;

    public function setMessage(string $value):void;

    public function setDismissible(bool $value):void;

    public function setPersistent(bool $value):void;

    public function setClasses(string $value):void;

    public function getId():string;

    public function isDismissible():bool;

    public function isPersistent():bool;

    public function printHtml():void;
}
