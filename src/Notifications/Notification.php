<?php
namespace PbxBlowball\Notifications;

if (! defined ( 'ABSPATH' )) {
	exit (); // Exit if accessed directly
}

/**
 * Class for representing wordpress notification
 */
class Notification implements NotificationInterface
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $classes = '';

    /**
     * @var string
     */
    protected $type = 'info';

    /**
     * @var string|null
     */
    protected $title = null;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var bool
     */
    protected $dismissible = false;

    /**
     * @var bool
     */
    protected $persistent = false;

    /**
     * @var array<string>
     */
    private static $types = ['info', 'success', 'warning', 'error'];

    /**
     * Create a new notification
     *
     * @param string $message message
     * @param string $id unique id to identify notification
     */
    public function __construct(string $message, string $type, string $id = null)
    {
        $this->id = $id !== null ? $id : \uniqid();
        $this->message = $message;
        $this->setType($type);
    }

    public function setId(string $value):void
    {
        $this->id = $value;
    }

    public function setDismissible(bool $value):void
    {
        $this->dismissible = $value;
    }

    public function setPersistent(bool $value):void
    {
        $this->persistent = $value;
    }

    public function setTitle(string $value):void
    {
        $this->title = $value;
    }

    public function setMessage(string $value):void
    {
        $this->message = $value;
    }

    public function setType(string $value):void
    {
        if (!\in_array($value, self::$types))
            throw new \InvalidArgumentException('Type must be of ' . \implode(', ', self::$types));
        $this->type = $value;
    }

    public function setClasses(string $value):void
    {
        $this->classes = $value;
    }

    public function getId():string
    {
        return $this->id;
    }

    public function isDismissible():bool
    {
        return $this->dismissible;
    }

    public function isPersistent():bool
    {
        return $this->persistent;
    }

    public function printHtml():void
    {
        echo '<div class="notice notice-'.esc_html($this->type);
        if ($this->isDismissible())
            echo ' is-dismissible';
        echo esc_html($this->classes);
        echo '" id="'.esc_attr($this->id).'"><p>';
        if (!empty($this->title))
            echo '<strong>' . esc_html($this->title) . '</strong>';
        echo esc_html($this->message);
        echo '</p></div>';
    }
}
