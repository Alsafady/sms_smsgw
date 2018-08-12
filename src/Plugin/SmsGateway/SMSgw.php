<?php

namespace Drupal\sms_smsgw\Plugin\SmsGateway;

use Drupal\Core\Form\FormStateInterface;

use Drupal\sms\Plugin\SmsGatewayPluginBase;

use Drupal\sms\Message\SmsMessageInterface;
use Drupal\sms\Message\SmsMessageResult;
use Drupal\sms\Message\SmsDeliveryReport;
use Drupal\sms\Message\SmsMessageResultStatus;
use Drupal\sms\Message\SmsMessageReportStatus;


class Twilio extends SmsGatewayPluginBase {


  public function defaultConfiguration() {
    return [
      'strUserName' => '',
      'strPassword' => '',
      'strTagName' => '',
    ];
  }




  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $config = $this->getConfiguration();

    $form['sms_smsgw'] = [
      '#type'  => 'details',
      '#title' => $this->t('SMSgw.net'),
      '#open'  => TRUE,
    ];

    $form['sms_smsgw']['help'] = [
      '#type'  => 'html_tag',
      '#tag'   => 'p',
      '#value' => $this->t('To get your Sender ID, User, and Password information, Create an account here: <a href="https://www.smsgw.net">https://www.smsgw.net</a>.'),
    ];

    $form['sms_smsgw']['strTagName'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Sender ID'),
      '#default_value' => $config['strTagName'],
      '#description'   => t('The sender name of your SMSgw.net account.'),
      '#placeholder'   => 'XXXXXXXXXXXX',
      '#required'      => TRUE,
    ];

    $form['sms_smsgw']['strUserName'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('The username or Mobile number'),
      '#default_value' => $config['strUserName'],
      '#description'   => t('The username of your SMSgw.net account.'),
      '#required'      => TRUE,
    ];

    $form['sms_smsgw']['strPassword'] = [
      '#type'          => 'password',
      '#title'         => $this->t('Password'),
      '#default_value' => '',
      '#description'   => t('The password of your SMSgw.net account.'),
      '#required'      => TRUE,
    ];

    return $form;
  }





  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {

    $this->configuration['strUserName'] = trim($form_state->getValue('strUserName'));
    $this->configuration['strPassword'] = trim($form_state->getValue('strPassword'));
    $this->configuration['strTagName'] = $form_state->getValue('strTagName');
  }





  public function send(SmsMessageInterface $sms_message) {

    $result = new SmsMessageResult();
    $report = new SmsDeliveryReport();

    $uri = 'http://api.smsgw.net/SendBulkSMS';

    $options['form_params'] = [
      'strUserName'            => $this->configuration['strUserName'],
      'strPassword'            => $this->configuration['strPassword'],
      'strRecepientNumbers'    => $sms_message->getRecipients()[0],
      'strTagName'             => $this->configuration['strTagName'],
      'strMessage'             => $sms_message->getMessage(),

      //'domainName'       => \Drupal::request()->getHost()
    ];

    try {
      $response = $this->httpClient->request('post', $uri, $options);
    }
    catch (RequestException $e) {
      $report->setStatus(SmsMessageReportStatus::ERROR);
      $report->setStatusMessage($e->getMessage());
      return $result
        ->addReport($report)
        ->setError(SmsMessageResultStatus::ERROR)
        ->setErrorMessage('The request failed for some reason.');
    }

    $status = $response->getStatusCode();
    if ($status == 200) {
      // Returned successful response, parsing it
      $resp = $response->getBody()->__toString();

      // Check if the sms delivery request was successful
      if ($resp == '1') {
        $report->setStatus(SmsMessageReportStatus::QUEUED);
      }
      else {
        $report->setStatus(SmsMessageReportStatus::ERROR);
        $report->setStatusMessage('Sending message failed with error code: ' . $resp);
      }
    }

    $report->setRecipient($sms_message->getRecipients()[0]);

    $result->addReport($report);

    return $result;
  }




}
