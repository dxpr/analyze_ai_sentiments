<?php

namespace Drupal\analyze_ai_sentiment\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides a form for deleting a sentiment.
 */
class DeleteSentimentForm extends ConfirmFormBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The sentiment ID to delete.
   *
   * @var string
   */
  protected $sentimentId;

  /**
   * Constructs a DeleteSentimentForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'analyze_ai_sentiment_delete_sentiment';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $sentiment_id = NULL) {
    $this->sentimentId = $sentiment_id;
    $form = parent::buildForm($form, $form_state);
    
    // Add warning class to confirm button
    $form['actions']['submit']['#attributes']['class'][] = 'button--danger';
    
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $config = $this->configFactory->get('analyze_ai_sentiment.settings');
    $sentiments = $config->get('sentiments');
    $sentiment = $sentiments[$this->sentimentId] ?? NULL;

    return $this->t('Are you sure you want to delete the sentiment metric %label?', [
      '%label' => $sentiment ? $sentiment['label'] : $this->sentimentId,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone. All content analysis results using this sentiment metric will be affected.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Keep sentiment metric');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete sentiment metric');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('analyze_ai_sentiment.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('analyze_ai_sentiment.settings');
    $sentiments = $config->get('sentiments');

    if (isset($sentiments[$this->sentimentId])) {
      $label = $sentiments[$this->sentimentId]['label'];
      unset($sentiments[$this->sentimentId]);
      $config->set('sentiments', $sentiments)->save();
      $this->messenger()->addStatus($this->t('The sentiment %label has been deleted.', [
        '%label' => $label,
      ]));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

} 