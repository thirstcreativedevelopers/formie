<?php
namespace verbb\formie\integrations\emailmarketing;

use verbb\formie\Formie;
use verbb\formie\base\Integration;
use verbb\formie\base\EmailMarketing;
use verbb\formie\elements\Form;
use verbb\formie\elements\Submission;
use verbb\formie\errors\IntegrationException;
use verbb\formie\events\SendIntegrationPayloadEvent;
use verbb\formie\models\IntegrationField;
use verbb\formie\models\EmailMarketingList;

use Craft;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\web\View;

class ConstantContact extends EmailMarketing
{
    // Properties
    // =========================================================================

    public $apiKey;
    public $appSecret;


    // OAuth Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public static function supportsOauthConnection(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getAuthorizeUrl(): string
    {
        return 'https://api.cc.email/v3/idfed';
    }

    /**
     * @inheritDoc
     */
    public function getAccessTokenUrl(): string
    {
        return 'https://idfed.constantcontact.com/as/token.oauth2';
    }

    /**
     * @inheritDoc
     */
    public function getClientId(): string
    {
        return $this->settings['apiKey'] ?? '';
    }

    /**
     * @inheritDoc
     */
    public function getClientSecret(): string
    {
        return $this->settings['appSecret'] ?? '';
    }

    /**
     * @inheritDoc
     */
    public function getOauthScope(): array
    {
        return ['contact_data'];
    }


    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public static function displayName(): string
    {
        return Craft::t('formie', 'Constant Contact');
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return Craft::t('formie', 'Sign up users to your Constant Contact lists to grow your audience for campaigns.');
    }

    /**
     * @inheritDoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['apiKey', 'appSecret'], 'required'];

        return $rules;
    }

    /**
     * @inheritDoc
     */
    public function fetchFormSettings()
    {
        $settings = [];

        try {
            $response = $this->_request('GET', 'contact_lists');
            $lists = $response['lists'] ?? [];

            foreach ($lists as $list) {
                // While we're at it, fetch the fields for the list
                $response = $this->_request('GET', 'contact_custom_fields');

                $listFields = [
                    new IntegrationField([
                        'handle' => 'email',
                        'name' => Craft::t('formie', 'Email'),
                        'required' => true,
                    ]),
                    new IntegrationField([
                        'handle' => 'first_name',
                        'name' => Craft::t('formie', 'First Name'),
                    ]),
                    new IntegrationField([
                        'handle' => 'last_name',
                        'name' => Craft::t('formie', 'Last Name'),
                    ]),
                    new IntegrationField([
                        'handle' => 'job_title',
                        'name' => Craft::t('formie', 'Job Title'),
                    ]),
                    new IntegrationField([
                        'handle' => 'company_name',
                        'name' => Craft::t('formie', 'Company Name'),
                    ]),
                    new IntegrationField([
                        'handle' => 'phone_number',
                        'name' => Craft::t('formie', 'Phone Number'),
                    ]),
                    new IntegrationField([
                        'handle' => 'anniversary',
                        'name' => Craft::t('formie', 'Anniversary'),
                    ]),
                ];

                $fields = $response['custom_fields'] ?? [];

                foreach ($fields as $field) {
                    $listFields[] = new IntegrationField([
                        'handle' => $field['custom_field_id'],
                        'name' => $field['label'],
                        'type' => $field['type'],
                    ]);
                }

                $settings['lists'][] = new EmailMarketingList([
                    'id' => $list['list_id'],
                    'name' => $list['name'],
                    'fields' => $listFields,
                ]);
            }
        } catch (\Throwable $e) {
            Integration::error($this, Craft::t('formie', 'API error: “{message}” {file}:{line}', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]), true);
        }

        return $settings;
    }

    /**
     * @inheritDoc
     */
    public function sendPayload(Submission $submission): bool
    {
        try {
            $fieldValues = $this->getFieldMappingValues($submission, $this->fieldMapping);

            // Pull out email, as it needs to be top level
            $email = ArrayHelper::remove($fieldValues, 'email');

            // Deal with custom fields
            $customFields = [];

            foreach ($fieldValues as $key => $fieldValue) {
                if (strstr($key, '-')) {
                    $customFields[] = [
                        'custom_field_id' => $key,
                        'value' => ArrayHelper::remove($fieldValues, $key),
                    ];
                }
            }

            $payload = array_merge([
                'email_address' => $email,
                'list_memberships' => [$this->listId],
                'custom_fields' => $customFields,
            ], $fieldValues);

            // Allow events to cancel sending
            if (!$this->beforeSendPayload($submission, $payload)) {
                return false;
            }

            // Add or update
            $response = $this->_request('POST', 'contacts/sign_up_form', [
                'json' => $payload,
            ]);

            // Allow events to say the response is invalid
            if (!$this->afterSendPayload($submission, $payload, $response)) {
                return false;
            }

            $contactId = $response['contact_id'] ?? '';

            if (!$contactId) {
                Integration::error($this, Craft::t('formie', 'API error: “{response}”', [
                    'response' => Json::encode($response),
                ]), true);

                return false;
            }
        } catch (\Throwable $e) {
            Integration::error($this, Craft::t('formie', 'API error: “{message}” {file}:{line}', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]), true);

            return false;
        }

        return true;
    }


    // Private Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    private function _getClient()
    {
        if ($this->_client) {
            return $this->_client;
        }

        $token = $this->getToken();

        $this->_client = Craft::createGuzzleClient([
            'base_uri' => 'https://api.cc.email/v3/',
            'headers' => [
                'Authorization' => 'Bearer ' . $token->accessToken ?? '',
                'Content-Type' => 'application/json',
            ],
        ]);

        // Always provide an authenticated client - so check first.
        // We can't always rely on the EOL of the token.
        try {
            $response = $this->_request('GET', 'contact_lists');
        } catch (\Throwable $e) {
            if ($e->getCode() === 401) {
                // Force-refresh the token
                Formie::$plugin->getTokens()->refreshToken($token, true);

                // Then try again, with the new access token
                $this->_client = Craft::createGuzzleClient([
                    'base_uri' => 'https://api.cc.email/v3/',
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token->accessToken ?? '',
                        'Content-Type' => 'application/json',
                    ],
                ]);
            }
        }

        return $this->_client;
    }

    /**
     * @inheritDoc
     */
    private function _request(string $method, string $uri, array $options = [])
    {
        $response = $this->_getClient()->request($method, trim($uri, '/'), $options);

        return Json::decode((string)$response->getBody());
    }
}