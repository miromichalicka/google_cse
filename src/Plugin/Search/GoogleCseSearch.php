<?php

namespace Drupal\google_cse\Plugin\Search;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\UrlGeneratorTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\search\Plugin\ConfigurableSearchPluginBase;

/**
 * Executes a Google CSE keyword search.
 *
 * @SearchPlugin(
 *   id = "google_cse_search",
 *   title = @Translation("Google CSE search type")
 * )
 */
class GoogleCseSearch extends ConfigurableSearchPluginBase implements AccessibleInterface {

  use UrlGeneratorTrait;

  /**
   * {@inheritdoc}
   */
  public function setSearch($keywords, array $parameters, array $attributes) {
    if (empty($parameters['search_conditions'])) {
      $parameters['search_conditions'] = '';
    }
    parent::setSearch($keywords, $parameters, $attributes);
  }

  /**
   * Verifies if the given parameters are valid enough to execute a search for.
   *
   * @return bool
   *   TRUE if there are keywords or search conditions in the query.
   */
  public function isSearchExecutable() {
    return (bool) ($this->keywords || !empty($this->searchParameters['search_conditions']));
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation = 'view', AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowedIf(!empty($account) && $account->hasPermission('search Google CSE'))->cachePerPermissions();
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * Execute the search.
   *
   * This is a dummy search, so when search "executes", we just return a dummy
   * result containing the keywords and a list of conditions.
   *
   * @return array
   *   A structured list of search results
   */
  public function execute() {
    // @todo Implement properly.
    $keys = $this->keywords;
    $conditions = $this->searchParameters['search_conditions'];
    if (\Drupal::config('google_cse.settings')->get('use_adv')) {
      // Firstly, load the needed modules.
      module_load_include('inc', 'google_cse', 'google_cse_adv/google_cse_adv');
      // And get the google results.
      $response = google_cse_adv_service($keys);
      $results = google_cse_adv_response_results($response[0], $keys, $conditions);

      // Allow other modules to alter the keys.
      \Drupal::moduleHandler()->alter('google_cse_searched_keys', $keys);

      // Allow other modules to alter the results.
      \Drupal::moduleHandler()->alter('google_cse_searched_results', $results);

      return $results;
    }

    $results = [];
    if (!$this->isSearchExecutable()) {
      return $results;
    }
    return [
      [
        'link' => Url::fromRoute('<front>')->toString(),
        'type' => 'Dummy result type',
        'title' => 'Dummy title',
        'snippet' => new FormattableMarkup("Dummy search snippet to display. Keywords: @keywords\n\nConditions: @search_parameters", ['@keywords' => $this->keywords, '@search_parameters' => print_r($this->searchParameters, TRUE)]),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildResults() {
    // @todo Implement properly.
    $results = $this->execute();
    $output['prefix']['#markup'] = '<h2>Test page text is here</h2> <ol class="search-results">';

    foreach ($results as $entry) {
      $output[] = [
        '#theme' => 'search_result',
        '#result' => $entry,
        '#plugin_id' => 'search_extra_type_search',
      ];
    }
    $pager = ['#type' => 'pager'];
    $output['suffix']['#markup'] = '</ol>' . drupal_render($pager);

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Output form for defining rank factor weights.
    $form['extra_type_settings'] = [
      '#type' => 'fieldset',
      '#title' => t('Extra type settings'),
      '#tree' => TRUE,
    ];

    $form['extra_type_settings']['boost'] = [
      '#type' => 'select',
      '#title' => t('Boost method'),
      '#options' => [
        'bi' => t('Bistromathic'),
        'ii' => t('Infinite Improbability'),
      ],
      '#default_value' => $this->configuration['boost'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['boost'] = $form_state->getValue(['extra_type_settings', 'boost']);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'boost' => 'bi',
    ];
  }

}
