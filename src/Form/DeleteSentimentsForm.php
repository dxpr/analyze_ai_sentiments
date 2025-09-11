<?php

namespace Drupal\analyze_ai_sentiments\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides a form for deleting a sentiments.
 */
class DeleteSentimentsForm extends ConfirmFormBase {
  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The sentiments ID to delete.
   *
   * @var string
   */
  protected $sentimentsId;

  /**
   * Constructs a DeleteSentimentsForm object.
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
  public static function create(ContainerInterface $container): static {
    /** @var static */
    return new self(
          $container->get('config.factory')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'analyze_ai_sentiments_delete_sentiments';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $sentiments_id = NULL): array {
    /** @var array<string, mixed> $form */
    $this->sentimentsId = $sentiments_id;
    $form = parent::buildForm($form, $form_state);

    // Add warning class to confirm button.
    $form['actions']['submit']['#attributes']['class'][] = 'button--danger';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $config = $this->configFactory->get('analyze_ai_sentiments.settings');
    $sentiments = $config->get('sentiments');
    $sentiments = $sentiments[$this->sentimentsId] ?? NULL;

    return $this->t('Are you sure you want to delete the sentiments metric %label?', [
      '%label' => $sentiments ? $sentiments['label'] : $this->sentimentsId,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone. All content analysis results using this sentiments metric will be affected.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Keep sentiments metric');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete sentiments metric');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('analyze_ai_sentiments.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var array<string, mixed> $form */
    $config = $this->configFactory->getEditable('analyze_ai_sentiments.settings');
    $sentiments = $config->get('sentiments');

    if (isset($sentiments[$this->sentimentsId])) {
      $label = $sentiments[$this->sentimentsId]['label'];
      unset($sentiments[$this->sentimentsId]);
      $config->set('sentiments', $sentiments)->save();
      $this->messenger()->addStatus($this->t('The sentiments %label has been deleted.', [
        '%label' => $label,
      ]));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
