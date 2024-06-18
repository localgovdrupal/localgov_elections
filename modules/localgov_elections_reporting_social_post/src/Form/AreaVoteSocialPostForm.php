<?php

declare(strict_types=1);

namespace Drupal\localgov_elections_reporting_social_post\Form;

use Abraham\TwitterOAuth\TwitterOAuth;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;
use Drupal\node\NodeInterface;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_api\User\UserManagerInterface;
use Drupal\social_post\User\UserManager;
use Drupal\social_post_twitter\TwitterPostManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an Election Social API Integration form.
 */
class AreaVoteSocialPostForm extends FormBase {

  /**
   * Localgov Elections Social API config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * User manager service.
   *
   * @var \Drupal\social_post\User\UserManager|UserManagerInterface
   */
  protected UserManager $userManager;

  /**
   * Twitter post manager service.
   *
   * @var \Drupal\social_post_twitter\TwitterPostManagerInterface
   */
  protected TwitterPostManagerInterface $twitterPostManager;

  /**
   * Twitter OAUTH client.
   *
   * @var ?TwitterOAuth
   */
  protected ?TwitterOAuth $client;

  /**
   * Core token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $tokenService;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs an area vote social post form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory service.
   * @param \Drupal\social_api\User\UserManagerInterface $userManager
   *   User manager service (social api)
   * @param \Drupal\social_post_twitter\TwitterPostManagerInterface $twitterPostManager
   *   Twitter post manager service (social api)
   * @param \Drupal\social_api\Plugin\NetworkManager $networkManager
   *   Network manager service (social api).
   * @param \Drupal\Core\Utility\Token $token
   *   Token service.
   * @param \Drupal\Core\Session\AccountProxy $currentUser
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    UserManagerInterface $userManager,
    TwitterPostManagerInterface $twitterPostManager,
    NetworkManager $networkManager,
    Token $token,
    AccountProxy $currentUser,
    EntityTypeManagerInterface $entityTypeManager,
    MessengerInterface $messenger,
  ) {
    $this->config = $configFactory->get('localgov_elections_reporting_social_post.settings');
    $this->userManager = $userManager;
    $this->twitterPostManager = $twitterPostManager;
    $this->messenger = $messenger;

    try {
      // This might not work.
      $this->client = $networkManager->createInstance('social_post_twitter')->getSdk();
    }
    catch (\Exception | \Throwable $e) {
      $this->messenger->addError("Could not initialise Twitter integration. Have you set up API keys?");
    }
    $this->tokenService = $token;
    $this->currentUser = $currentUser;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('config.factory'),
        $container->get('social_post.user_manager'),
        $container->get('twitter_post.manager'),
        $container->get('plugin.network.manager'),
        $container->get('token'),
        $container->get('current_user'),
        $container->get('entity_type.manager'),
        $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'localgov_elections_reporting_social_post_area_vote_social_post';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL): array {
    if ($node->bundle() != "division_vote") {
      $this->messenger()->addError("Can only post from area vote nodes");
      return $form;
    }

    if ($node->get('field_votes_finalised')->value == "0") {
      $this->messenger()->addWarning($this->t("The votes have not been finalised for this vote yet."));
    }
    $form['#node'] = $node;
    $accounts = $this->getTwitterAccounts();
    if (count($accounts) > 0) {

      if ($preview = $form_state->get('preview')) {
        $preview_length = strlen($preview);
        if ($preview_length > 280) {
          $preview_description = $preview_length . '/280 characters. You will need to edit this so it meets the maximum character length';
        }
        else {
          $preview_description = $preview_length . '/280 characters';
        }

        $form['preview'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Preview'),
          '#default_value' => $preview,
          '#disabled' => TRUE,
          '#description' => $preview_description,
        ];
      }
      else {
        $form['account'] = [
          '#title' => $this->t('Account'),
          '#type' => 'select',
          '#multiple' => TRUE,
          '#options' => $accounts,
          '#required' => TRUE,
          '#default_value' => $form_state->getValue('account'),
        ];
        $form['message'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Message'),
          '#description' => $this->t("This field supports tokens."),
          '#default_value' => $form_state->getValue('message') ?? $this->config->get('message_template'),
          '#required' => TRUE,
        ];
        $form['token_tree'] = [
          '#theme' => 'token_tree_link',
          '#token_types' => ['user', 'node'],
          '#show_restricted' => TRUE,
          '#weight' => 100,
        ];
      }

      if ($preview = $form_state->get('preview')) {
        $preview_length = strlen($preview);
        $form['actions']['edit'] = [
          '#type' => 'actions',
          'submit' => [
            '#type' => 'submit',
            '#value' => $this->t('Edit'),
            '#submit' => ['::edit'],
            '#limit_validation_errors' => [],
          ],
        ];
        if ($preview_length <= 280) {
          $form['actions']['submit'] = [
            '#type' => 'actions',
            'submit' => [
              '#type' => 'submit',
              '#value' => $this->t('Tweet'),
            ],
          ];
        }
      }
      else {
        $form['actions']['preview'] = [
          '#type' => 'actions',
          'submit' => [
            '#type' => 'submit',
            '#value' => $this->t('Preview'),
            '#submit' => ['::preview'],
          ],
        ];
      }

    }
    else {
      $profile = $this->currentUser;
      $url = Link::fromTextAndUrl("here", Url::fromRoute('entity.user.edit_form', ['user' => $profile->id()]))->toString();
      $this->messenger
        ->addError($this->t("You do not appear to have linked any Twitter accounts yet. You can do this from your user profile @here.", [
          "@here" => $url,
        ]));
    }
    return $form;
  }

  /**
   * Shows the 'edit' screen.
   *
   * @param mixed $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return void
   *   Returns nothing.
   */
  public function edit($form, FormStateInterface $form_state) {
    $form_state->set('preview', NULL);
    $data = $form_state->get('original_data');
    $form_state->setValue('account', $data['account']);
    $form_state->setValue('message', $data['message']);
    $form_state->setRebuild();
  }

  /**
   * Shows the 'preview' screen.
   *
   * @param mixed $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function preview($form, FormStateInterface $form_state) {
    $message = $this->getTokenizedMessage($form, $form_state);
    $form_state->set('preview', $message);
    $form_state->set('original_data', [
      'account' => $form_state->getValue('account'),
      'message' => $form_state->getValue('message'),
    ]);

    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ($preview = $form_state->get('preview')) {
      $length = strlen($preview);
      if ($length > 280) {
        $form_state->setErrorByName('message', $this->t("Final message exceeds 280 characters"));
      }
    }
  }

  /**
   * Helper function to get tokenized message.
   *
   * @param mixed $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string
   *   The tokenised message.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getTokenizedMessage($form, FormStateInterface $form_state) {
    $message = $form_state->getValue('message');
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    return $this->tokenService->replace($message,
        [
          'node' => $form['#node'],
          'user' => $user,
        ]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $keys = array_keys($form_state->get('original_data')['account']);
    $accounts = array_filter($this->userManager->getAccounts('social_post_twitter'), function ($obj) use ($keys) {
      return in_array($obj->id(), $keys);
    });

    $message = $form_state->getValue('preview');
    foreach ($accounts as $account) {
      $token = json_decode($account->getToken());
      $this->client->setOauthToken($token->oauth_token, $token->oauth_token_secret);
      $twitter_client = $this->twitterPostManager->setClient($this->client);
      $post = $twitter_client->doPost($message);

      // @todo See if we can handle tweet sending validation better
      // This is a bit hmm. Can return true even if the tweet returns 403.
      // We only get a bool so not much we can do
      // E.g. duplicate tweets return true but Twitter
      // won't actually post the tweet if it's a duplicate.
      if ($post) {
        $this->messenger()->addStatus("Tweet has been attempted");
      }
      else {
        $this->messenger()->addWarning("There seems to have been an issue with sending the Tweet.");
      }
    }
  }

  /**
   * Helper function to get Twitter accounts of the current user.
   *
   * @return array
   *   An array of accounts.
   */
  protected function getTwitterAccounts() {
    $return_array = [];
    $accounts = $this->userManager->getAccounts('social_post_twitter');
    foreach ($accounts as $acc) {
      /** @var \Drupal\social_post\Entity\SocialPost $acc */
      $return_array[$acc->id()] = $acc->getName();
    }
    return $return_array;
  }

}
