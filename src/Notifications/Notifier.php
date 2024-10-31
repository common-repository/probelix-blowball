<?php
namespace PbxBlowball\Notifications;

if (! defined ( 'ABSPATH' )) {
	exit (); // Exit if accessed directly
}

/**
 * Class for working with wordpress notification messages
 */
class Notifier
{
	/**
	 * @var int
	 */
    private static $counter = 0;

	/**
	 * @var string
	 */
    protected $transientName;

	/**
	 * @var array<NotificationInterface>
	 */
    protected $notifications;

    public function __construct(string $prefix = '')
    {
        $this->notifications = [];
        $this->transientName = $prefix . "notifications_" . static::$counter++;
        $this->loadNotifications();

        add_action('admin_notices', [$this, 'renderNotifications']);
        add_action('wp_ajax_dismiss_admin_notification', [$this, 'dismissNotification']);
        add_action('admin_footer', [$this, 'enqueueScript']);
    }

    /**
     * Dispatch a simple notification
     *
     * @param string $type notification type
     * @param string $message notification message
     * @return boolean
     */
    public function notify(string $type, string $message)
    {
        return $this->dispatch(new Notification($message, $type));
    }

    /**
     * Dispatch a simple info notificattion
     *
     * @param string $message
     * @return boolean
     */
    public function info(string $message)
    {
        return $this->notify('info', $message);
    }

    /**
     * Dispatch a simple success notificattion
     *
     * @param string $message
     * @return boolean
     */
    public function success(string $message)
    {
        return $this->notify('success', $message);
    }

    /**
     * Dispatch a simple warning notificattion
     *
     * @param string $message
     * @return boolean
     */
    public function warning(string $message)
    {
        return $this->notify('warning', $message);
    }

    /**
     * Dispatch a simple error notificattion
     *
     * @param string $message
     * @return boolean
     */
    public function error(string $message)
    {
        return $this->notify('error', $message);
    }

    /**
     * Dispatch a new notification
     */
    public function dispatch(NotificationInterface $notification):bool
    {
        $this->notifications[$notification->getId()] = $notification;
        $this->saveNotifications();
        return true;
    }
    /**
     * Whether notification with given ID already exists
     *
     * @param string $id
     * @return boolean
     */
    public function containsNotification(string $id)
    {
        return array_key_exists($id, $this->notifications);
    }

    /**
     * @param string $id
     * @return void
     */
    public function removeNotification(string $id)
    {
        if (!$this->containsNotification($id)) {
            return;
        }
        unset($this->notifications[$id]);
        $this->saveNotifications();
    }

    /**
     * @return void
     */
    public function dismissNotification()
    {
        $id = filter_input(INPUT_POST, 'id');
        $this->removeNotification($id);
        return;
    }

    /**
     * @return void
     */
    public function enqueueScript()
    {
        if (count($this->notifications) === 0) {
            return;
        }
        ?>
            <script>
                jQuery(document).ready(function($){
                    $('.notice').on('click','.notice-dismiss',function(e){
                        $.post(ajaxurl,{
                            action: 'dismiss_admin_notification',
                            id: $(this).parent().attr('id')
                        });
                    });
                });
            </script>
        <?php
    }

    /**
     * @return void
     */
    public function loadNotifications()
    {
        $notifications = get_transient($this->transientName);
        if (is_array($notifications)) {
            $this->notifications = $notifications;
        } else {
            $this->notifications = [];
        }
    }

    public function saveNotifications():bool
    {
        return set_transient($this->transientName, $this->notifications);
    }

    public function clearNotifications():void
    {
        $this->notifications = [];
        $this->saveNotifications();
    }

    public function renderNotifications():void
    {
        if (empty($this->notifications)) {
            return;
        }
        foreach ($this->notifications as $n) {
            $n->printHtml();
            if (!$n->isPersistent()) {
                $this->removeNotification($n->getId());
            }
        }
    }
}
