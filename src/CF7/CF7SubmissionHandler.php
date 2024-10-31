<?php

namespace PbxBlowball\CF7;

use PbxBlowball\Client\PbxBlowballClient;
use PbxBlowball\Notifications\Notification;
use PbxBlowball\Notifications\Notifier;
use PbxVendor\Psr\Log\LoggerInterface;
use WPCF7_Submission;

if (! defined ( 'ABSPATH' )) {
	exit (); // Exit if accessed directly
}

/**
 * Handler for Contact Form 7 Submissions
 */
class CF7SubmissionHandler
{
    /**
     * @var PbxBlowballClient
     */
    private $api;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Notifier
     */
    private $notifier;

	public function __construct(PbxBlowballClient $api, LoggerInterface $logger, Notifier $notifier)
	{
        $this->api = $api;
        $this->logger = $logger;
        $this->notifier = $notifier;
	}

    /**
     * @param mixed $form (WPCF7_ContactForm)
     * @return void
     */
    public function handleForm($form):void
    {
        $formData = $this->getCF7FormData();
        if (is_null($formData))
            return;
        $options = CF7Helper::getFormOptions($form->id);

        if (!empty($options['requireField']) && empty($_POST[$options['requireField']])) {
            $this->logger->debug('Did not process data: Required field not set.', [
                'formId' => $form->id,
                'options' => $options
            ]);
            return;
        }

        if ((array_key_exists('conditionField',$options))&&(!empty($options['conditionField']))){
            if (!array_key_exists($options['conditionField'],$formData)){
                $this->logger->debug('Did not process data: ConditionField not found.', [
                    'formId' => $form->id,
                    'options' => $options
                ]);
                return;
            }
            $value = $formData[$options['conditionField']];
            if (is_array($value))
                $value = $value[0];
            if (empty($value)){
                $this->logger->debug('Did not process data: ConditionField not set.', [
                    'formId' => $form->id,
                    'options' => $options
                ]);
                return;
            }
        }


        if (empty($options['emailField'])) {
            $message = sprintf(
                'Form config for form "<a href="%s">%s</a>" is incomplete.',
                esc_url(admin_url('admin.php?page=wpcf7&post=' . $form->id)),
                $form->title()
            );
            $notification = new Notification($message, 'error', 'error.incomplete-config.' . $form->id);
            $notification->setTitle('CF7 to blowball: ');
            $notification->setDismissible(true);
            $notification->setPersistent(true);
            $this->notifier->dispatch($notification);

            $this->logger->error(
                'Did not process data: Missing configuration (list ID, form ID, email field).',
                [
                    'formId' => $form->id,
                    'formData' => $formData,
                    'options' => $options
                ]
            );
            return;
        }

        $email = $formData[$options['emailField']];

        if (empty($email)) {
            $this->logger->error('Did not process data: No email found.', [
                'formId' => $form->id,
                'formData' => $formData,
                'options' => $options
            ]);
            return;
        }

        $data = ['email' => $email];
        if (array_key_exists($options['fnameField'],$formData))
            $data['fname'] = $formData[$options['fnameField']];
        if (array_key_exists($options['lnameField'],$formData))
            $data['lname'] = $formData[$options['lnameField']];
        if (array_key_exists($options['storeField'],$formData))
            $data['store'] = $formData[$options['storeField']];

        if (array_key_exists('source', $options)&&(!empty($options['source'])))
            $data['lead_source'] = $options['source'];

        if (array_key_exists('tags', $options)&&(!empty($options['tags'])))
            $data['@tags'] = $options['tags'];

        try{
            $res = $this->api->createLead($data);
        }catch(\Exception $e){
            $this->logger->error('Failed creating lead', [$data, $e->getMessage()]);
        }
    }

    /**
     * @return array<mixed>|null
     */
    private function getCF7FormData()
    {
        if(class_exists('WPCF7_Submission')){
            $submission = WPCF7_Submission::get_instance();
            if (isset($submission) == false)
                return null;
            return $submission->get_posted_data();
        }
        return null;
    }
}
