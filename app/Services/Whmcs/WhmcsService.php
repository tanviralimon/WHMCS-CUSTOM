<?php

namespace App\Services\Whmcs;

use Illuminate\Support\Facades\Cache;

/**
 * High-level typed service for all WHMCS API actions.
 * Every method is typed and documented — controllers never call WhmcsClient directly.
 */
class WhmcsService
{
    protected WhmcsClient $client;

    public function __construct(WhmcsClient $client)
    {
        $this->client = $client;
    }

    // ─── Auth ──────────────────────────────────────────────

    public function validateLogin(string $email, string $password): array
    {
        return $this->client->call('ValidateLogin', [
            'email'     => $email,
            'password2' => $password,
        ]);
    }

    // ─── Client / Account ──────────────────────────────────

    public function getClientsDetails(int $clientId): array
    {
        return $this->client->callSafe('GetClientsDetails', [
            'clientid' => $clientId,
            'stats'    => true,
        ]);
    }

    public function updateClient(int $clientId, array $data): array
    {
        return $this->client->call('UpdateClient', array_merge(['clientid' => $clientId], $data));
    }

    public function addClient(array $data): array
    {
        return $this->client->call('AddClient', $data);
    }

    public function getContacts(int $clientId): array
    {
        return $this->client->callSafe('GetContacts', ['userid' => $clientId]);
    }

    public function addContact(int $clientId, array $data): array
    {
        return $this->client->call('AddContact', array_merge(['clientid' => $clientId], $data));
    }

    public function updateContact(int $contactId, array $data): array
    {
        return $this->client->call('UpdateContact', array_merge(['contactid' => $contactId], $data));
    }

    public function deleteContact(int $contactId): array
    {
        return $this->client->call('DeleteContact', ['contactid' => $contactId]);
    }

    // ─── Services / Products ───────────────────────────────

    public function getClientsProducts(int $clientId, int $offset = 0, int $limit = 25, ?string $status = null): array
    {
        $params = [
            'clientid'   => $clientId,
            'limitstart' => $offset,
            'limitnum'   => $limit,
        ];
        if ($status) {
            $params['status'] = ucfirst($status);
        }
        return $this->client->callSafe('GetClientsProducts', $params);
    }

    public function getClientProduct(int $clientId, int $serviceId): array
    {
        return $this->client->callSafe('GetClientsProducts', [
            'clientid'  => $clientId,
            'serviceid' => $serviceId,
            'stats'     => true,
        ]);
    }

    public function addCancelRequest(int $serviceId, string $type = 'Immediate', string $reason = ''): array
    {
        return $this->client->call('AddCancelRequest', [
            'serviceid' => $serviceId,
            'type'      => $type,
            'reason'    => $reason,
        ]);
    }

    public function upgradeProduct(int $serviceId, string $type, int $newProductId, string $paymentMethod, string $newBillingCycle = ''): array
    {
        $params = [
            'serviceid'       => $serviceId,
            'type'            => $type, // 'product' or 'configoptions'
            'newproductid'    => $newProductId,
            'paymentmethod'   => $paymentMethod,
        ];
        if ($newBillingCycle) {
            $params['newproductbillingcycle'] = $newBillingCycle;
        }
        return $this->client->call('UpgradeProduct', $params);
    }

    public function moduleChangePassword(int $serviceId): array
    {
        return $this->client->call('ModuleChangePassword', ['serviceid' => $serviceId]);
    }

    public function moduleCustom(int $serviceId, string $funcName): array
    {
        // Only allow safe actions
        $allowed = ['reboot', 'shutdown', 'boot', 'resetpassword', 'console', 'vnc'];
        if (!in_array(strtolower($funcName), $allowed)) {
            throw new \App\Exceptions\WhmcsApiException("Action '{$funcName}' is not permitted.", 'ModuleCustom');
        }
        return $this->client->call('ModuleCustom', [
            'serviceid'       => $serviceId,
            'func_name'       => $funcName,
        ]);
    }

    public function createSsoToken(int $clientId, int $serviceId, string $destination = 'clientarea:product_details'): array
    {
        return $this->client->call('CreateSsoToken', [
            'client_id'   => $clientId,
            'service_id'  => $serviceId,
            'destination' => $destination,
        ]);
    }

    /**
     * Create SSO token for client-level access (no service required).
     * Used for invoice payments, billing pages, etc.
     */
    public function createClientSsoToken(int $clientId, string $destination = 'clientarea:invoices'): array
    {
        return $this->client->call('CreateSsoToken', [
            'client_id'   => $clientId,
            'destination' => $destination,
        ]);
    }

    /**
     * Get service info from SSO proxy (module type, panel URLs, etc.)
     */
    public function getServiceInfo(int $serviceId, int $clientId = 0): array
    {
        return $this->client->callSsoProxySafe('GetServiceInfo', [
            'serviceid' => $serviceId,
            'clientid'  => $clientId,
        ]);
    }

    /**
     * Generate a direct SSO login URL for the control panel (SPanel, cPanel, etc.)
     * This goes directly to the panel, NOT to WHMCS clientarea.
     */
    public function panelSsoLogin(int $serviceId, int $clientId = 0, string $redirect = ''): array
    {
        $params = [
            'serviceid' => $serviceId,
            'clientid'  => $clientId,
        ];

        if ($redirect) {
            $params['redirect'] = $redirect;
        }

        return $this->client->callSsoProxy('SsoLogin', $params);
    }

    // ─── Addons ────────────────────────────────────────────

    public function getClientsAddons(int $clientId, int $offset = 0, int $limit = 25): array
    {
        return $this->client->callSafe('GetClientsAddons', [
            'clientid'   => $clientId,
            'limitstart' => $offset,
            'limitnum'   => $limit,
        ]);
    }

    // ─── Domains ───────────────────────────────────────────

    public function getClientsDomains(int $clientId, int $offset = 0, int $limit = 25, ?string $status = null): array
    {
        $params = [
            'clientid'   => $clientId,
            'limitstart' => $offset,
            'limitnum'   => $limit,
        ];
        if ($status) {
            $params['status'] = ucfirst($status);
        }
        return $this->client->callSafe('GetClientsDomains', $params);
    }

    public function getClientDomain(int $clientId, int $domainId): array
    {
        return $this->client->callSafe('GetClientsDomains', [
            'clientid' => $clientId,
            'domainid' => $domainId,
        ]);
    }

    public function updateClientDomain(int $domainId, array $data): array
    {
        return $this->client->call('UpdateClientDomain', array_merge(['domainid' => $domainId], $data));
    }

    public function domainRenew(int $domainId): array
    {
        return $this->client->call('DomainRenew', ['domainid' => $domainId]);
    }

    public function domainGetNameservers(int $domainId): array
    {
        return $this->client->callSafe('DomainGetNameservers', ['domainid' => $domainId]);
    }

    public function domainUpdateNameservers(int $domainId, array $nameservers): array
    {
        $params = ['domainid' => $domainId];
        foreach ($nameservers as $i => $ns) {
            $params['ns' . ($i + 1)] = $ns;
        }
        return $this->client->call('DomainUpdateNameservers', $params);
    }

    public function domainGetLockingStatus(int $domainId): array
    {
        return $this->client->callSafe('DomainGetLockingStatus', ['domainid' => $domainId]);
    }

    public function domainUpdateLockingStatus(int $domainId, bool $lock): array
    {
        return $this->client->call('DomainUpdateLockingStatus', [
            'domainid'   => $domainId,
            'lockstatus' => $lock ? 1 : 0,
        ]);
    }

    public function domainGetEPPCode(int $domainId): array
    {
        return $this->client->callSafe('DomainRequestEPP', ['domainid' => $domainId]);
    }

    public function domainWhois(string $domain): array
    {
        return $this->client->callSafe('DomainWhois', ['domain' => $domain]);
    }

    public function domainGetWhoisInfo(int $domainId): array
    {
        return $this->client->callSafe('DomainGetWhoisInfo', ['domainid' => $domainId]);
    }

    public function domainUpdateWhoisInfo(int $domainId, array $contactDetails): array
    {
        $params = ['domainid' => $domainId];

        // WHMCS expects contactdetails[SectionName][FieldName] = value
        foreach ($contactDetails as $section => $fields) {
            if (is_array($fields)) {
                foreach ($fields as $field => $value) {
                    $params["contactdetails[{$section}][{$field}]"] = $value;
                }
            }
        }

        return $this->client->call('DomainUpdateWhoisInfo', $params);
    }

    public function domainGetDNS(int $domainId): array
    {
        return $this->client->callDnsProxySafe('GetDNS', ['domainid' => $domainId]);
    }

    public function domainSetDNS(int $domainId, array $records): array
    {
        return $this->client->callDnsProxy('SaveDNS', [
            'domainid'   => $domainId,
            'dnsrecords' => json_encode($records),
        ]);
    }

    // ─── Private Nameservers ───────────────────────────────

    public function domainRegisterNameserver(string $nameserver, string $ipaddress): array
    {
        return $this->client->call('RegisterNameserver', [
            'nameserver' => $nameserver,
            'ipaddress'  => $ipaddress,
        ]);
    }

    public function domainModifyNameserver(string $nameserver, string $currentIp, string $newIp): array
    {
        return $this->client->call('ModifyNameserver', [
            'nameserver'       => $nameserver,
            'currentipaddress' => $currentIp,
            'newipaddress'     => $newIp,
        ]);
    }

    public function domainDeleteNameserver(string $nameserver): array
    {
        return $this->client->call('DeleteNameserver', [
            'nameserver' => $nameserver,
        ]);
    }

    // ─── Invoices ──────────────────────────────────────────

    public function getInvoices(int $clientId, string $status = '', int $offset = 0, int $limit = 25, ?string $orderBy = null, string $order = 'desc'): array
    {
        $params = [
            'userid'     => $clientId,
            'limitstart' => $offset,
            'limitnum'   => $limit,
            'orderby'    => $orderBy ?? 'id',
            'order'      => $order,
        ];
        if ($status) {
            $params['status'] = $status;
        }
        return $this->client->callSafe('GetInvoices', $params);
    }

    public function getInvoice(int $invoiceId): array
    {
        return $this->client->callSafe('GetInvoice', ['invoiceid' => $invoiceId]);
    }

    // ─── Transactions ──────────────────────────────────────

    public function getTransactions(int $clientId, int $offset = 0, int $limit = 25): array
    {
        return $this->client->callSafe('GetTransactions', [
            'clientid'   => $clientId,
            'limitstart' => $offset,
            'limitnum'   => $limit,
        ]);
    }

    // ─── Quotes ────────────────────────────────────────────

    public function getQuotes(int $clientId, int $offset = 0, int $limit = 25): array
    {
        return $this->client->callSafe('GetQuotes', [
            'userid'     => $clientId,
            'limitstart' => $offset,
            'limitnum'   => $limit,
        ]);
    }

    public function getQuote(int $quoteId): array
    {
        return $this->client->callSafe('GetQuotes', ['quoteid' => $quoteId]);
    }

    public function acceptQuote(int $quoteId): array
    {
        return $this->client->call('AcceptQuote', ['quoteid' => $quoteId]);
    }

    // ─── Tickets ───────────────────────────────────────────

    public function getTickets(int $clientId, string $status = '', int $offset = 0, int $limit = 25): array
    {
        $params = [
            'clientid'   => $clientId,
            'limitstart' => $offset,
            'limitnum'   => $limit,
        ];
        if ($status) {
            $params['status'] = $status;
        }
        return $this->client->callSafe('GetTickets', $params);
    }

    public function getTicket(int $ticketId): array
    {
        return $this->client->callSafe('GetTicket', ['ticketid' => $ticketId]);
    }

    public function openTicket(int $clientId, int $deptId, string $subject, string $message, string $priority = 'Medium', array $attachments = [], ?int $serviceId = null, ?int $domainId = null): array
    {
        $params = [
            'clientid' => $clientId,
            'deptid'   => $deptId,
            'subject'  => $subject,
            'message'  => $message,
            'priority' => $priority,
        ];

        // Associate ticket with a specific service or domain
        if ($serviceId) {
            $params['serviceid'] = $serviceId;
        }
        if ($domainId) {
            $params['domainid'] = $domainId;
        }

        // Try with attachments first; if WHMCS rejects, retry without
        if (!empty($attachments)) {
            try {
                $params['attachments'] = base64_encode(json_encode($attachments));
                return $this->client->call('OpenTicket', $params, 30);
            } catch (\Exception $e) {
                // Attachment failed — send without and note in message
                unset($params['attachments']);
                $params['message'] .= "\n\n[Note: Attachments could not be uploaded — files may exceed server limits.]";
                return $this->client->call('OpenTicket', $params);
            }
        }

        return $this->client->call('OpenTicket', $params);
    }

    /**
     * Check if a payment proof ticket already exists for a given invoice.
     */
    public function hasPaymentProofTicket(int $clientId, int $invoiceId): bool
    {
        try {
            $result = $this->client->callSafe('GetTickets', [
                'clientid' => $clientId,
                'subject'  => "Payment Proof for Invoice #{$invoiceId}",
                'limitnum' => 1,
            ]);

            $tickets = $result['tickets']['ticket'] ?? [];
            foreach ($tickets as $t) {
                $subj = $t['subject'] ?? '';
                if (str_contains($subj, "Invoice #{$invoiceId}") && str_contains(strtolower($subj), 'payment proof')) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            // If check fails, allow upload (don't block user)
        }

        return false;
    }

    public function addTicketReply(int $ticketId, int $clientId, string $message, array $attachments = []): array
    {
        $params = [
            'ticketid' => $ticketId,
            'clientid' => $clientId,
            'message'  => $message,
        ];

        // Try with attachments first; if WHMCS rejects, retry without
        if (!empty($attachments)) {
            try {
                $params['attachments'] = base64_encode(json_encode($attachments));
                return $this->client->call('AddTicketReply', $params, 30);
            } catch (\Exception $e) {
                // Attachment failed — send without and note in message
                unset($params['attachments']);
                $params['message'] .= "\n\n[Note: Attachments could not be uploaded — files may exceed server limits.]";
                return $this->client->call('AddTicketReply', $params);
            }
        }

        return $this->client->call('AddTicketReply', $params);
    }

    public function closeTicket(int $ticketId): array
    {
        return $this->client->callSafe('UpdateTicket', [
            'ticketid' => $ticketId,
            'status'   => 'Closed',
        ]);
    }

    public function getSupportDepartments(): array
    {
        return Cache::remember('whmcs.departments', 3600, function () {
            return $this->client->callSafe('GetSupportDepartments');
        });
    }

    /**
     * Get ticket attachment upload configuration.
     * Reads from config/payment.php (must match WHMCS ticket settings).
     */
    public function getTicketUploadConfig(): array
    {
        $maxSizeMB  = (int) config('payment.ticket_max_file_size_mb', 2);
        $extensions = config('payment.ticket_allowed_extensions', 'jpg,gif,jpeg,png,txt,pdf');

        // Normalize: strip dots and spaces
        $extensions = implode(',', array_map('trim', explode(',', str_replace('.', '', $extensions))));

        return [
            'max_size_mb' => $maxSizeMB,
            'max_size_kb' => $maxSizeMB * 1024,
            'extensions'  => $extensions,
        ];
    }

    // ─── Products / Ordering ───────────────────────────────

    public function getProducts(?int $groupId = null): array
    {
        $params = [];
        if ($groupId) {
            $params['gid'] = $groupId;
        }
        return Cache::remember("whmcs.products.{$groupId}", 600, function () use ($params) {
            return $this->client->callSafe('GetProducts', $params);
        });
    }

    public function getProductGroups(): array
    {
        // GetProducts returns group info with each product; we extract unique groups
        // Only include groups that have at least one visible (non-hidden) product
        // Filters out: hidden products and hidden groups
        // Order is preserved as returned by WHMCS (which reflects admin sort order)
        return Cache::remember('whmcs.product_groups', 600, function () {
            // Get group names and hidden group IDs from the WHMCS database
            $groupNames   = $this->getProductGroupNames();
            $hiddenGroups = $this->getHiddenGroupIds();

            $products = $this->client->callSafe('GetProducts');
            $groups = [];
            $order = [];
            foreach ($products['products']['product'] ?? [] as $p) {
                if (!empty($p['hidden'])) continue;
                $gid = $p['gid'] ?? 0;
                // Skip hidden groups
                if (in_array($gid, $hiddenGroups)) continue;
                if ($gid && !isset($groups[$gid])) {
                    $groups[$gid] = [
                        'id'   => $gid,
                        'name' => $groupNames[$gid] ?? $p['groupname'] ?? 'Products',
                    ];
                    $order[] = $gid;
                }
            }
            // Return in WHMCS sort order
            $sorted = [];
            foreach ($order as $gid) {
                $sorted[] = $groups[$gid];
            }
            return $sorted;
        });
    }

    /**
     * Get product group names from the WHMCS database via the SSO proxy.
     * Returns an array of [gid => name].
     */
    public function getProductGroupNames(): array
    {
        return Cache::remember('whmcs.product_group_names', 3600, function () {
            $result = $this->client->callSsoProxySafe('GetProductGroups');
            $map = [];
            foreach ($result['groups'] ?? [] as $g) {
                $map[(int) $g['id']] = $g['name'];
            }
            return $map;
        });
    }

    /**
     * Get the set of hidden product group IDs from WHMCS.
     * Products in these groups should not be shown on the storefront.
     */
    public function getHiddenGroupIds(): array
    {
        return Cache::remember('whmcs.hidden_group_ids', 3600, function () {
            $result = $this->client->callSsoProxySafe('GetProductGroups');
            $ids = [];
            foreach ($result['groups'] ?? [] as $g) {
                if (!empty($g['hidden'])) {
                    $ids[] = (int) $g['id'];
                }
            }
            return $ids;
        });
    }

    public function getPromotions(?string $code = null): array
    {
        $params = [];
        if ($code) {
            $params['code'] = $code;
        }
        return $this->client->callSafe('GetPromotions', $params);
    }

    public function addOrder(int $clientId, array $orderData): array
    {
        return $this->client->call('AddOrder', array_merge([
            'clientid' => $clientId,
        ], $orderData));
    }

    public function getOrders(int $clientId, int $offset = 0, int $limit = 25): array
    {
        return $this->client->callSafe('GetOrders', [
            'userid'     => $clientId,
            'limitstart' => $offset,
            'limitnum'   => $limit,
        ]);
    }

    public function getOrder(int $orderId): array
    {
        return $this->client->callSafe('GetOrders', ['id' => $orderId]);
    }

    // ─── Announcements ─────────────────────────────────────

    public function getAnnouncements(int $offset = 0, int $limit = 25): array
    {
        return Cache::remember("whmcs.announcements.{$offset}.{$limit}", 300, function () use ($offset, $limit) {
            return $this->client->callSafe('GetAnnouncements', [
                'limitstart' => $offset,
                'limitnum'   => $limit,
            ]);
        });
    }

    public function getAnnouncement(int $id): array
    {
        return $this->client->callSafe('GetAnnouncements', ['id' => $id]);
    }

    // ─── Knowledgebase ─────────────────────────────────────

    public function getKnowledgebaseCategories(): array
    {
        return Cache::remember('whmcs.kb_categories', 600, function () {
            return $this->client->callSafe('GetKnowledgebaseCategories');
        });
    }

    public function getKnowledgebaseArticles(int $catId = 0, int $offset = 0, int $limit = 25): array
    {
        $cacheKey = "whmcs.kb_articles.{$catId}.{$offset}";
        return Cache::remember($cacheKey, 600, function () use ($catId, $offset, $limit) {
            $params = ['limitstart' => $offset, 'limitnum' => $limit];
            if ($catId) {
                $params['catid'] = $catId;
            }
            return $this->client->callSafe('GetKnowledgebaseArticles', $params);
        });
    }

    public function getKnowledgebaseArticle(int $articleId): array
    {
        return Cache::remember("whmcs.kb_article.{$articleId}", 600, function () use ($articleId) {
            return $this->client->callSafe('GetKnowledgebaseArticles', ['articleid' => $articleId]);
        });
    }

    // ─── Downloads ─────────────────────────────────────────

    public function getDownloads(int $catId = 0, int $offset = 0, int $limit = 25): array
    {
        $cacheKey = "whmcs.downloads.{$catId}.{$offset}";
        return Cache::remember($cacheKey, 600, function () use ($catId, $offset, $limit) {
            $params = ['limitstart' => $offset, 'limitnum' => $limit];
            if ($catId) {
                $params['catid'] = $catId;
            }
            return $this->client->callSafe('GetDownloads', $params);
        });
    }

    // ─── Affiliates ────────────────────────────────────────

    public function getAffiliates(int $clientId): array
    {
        return $this->client->callSafe('GetAffiliates', ['userid' => $clientId]);
    }

    // ─── Currencies ────────────────────────────────────────

    public function getCurrencies(): array
    {
        return Cache::remember('whmcs.currencies', 3600, function () {
            return $this->client->callSafe('GetCurrencies');
        });
    }

    // ─── Payment Methods ───────────────────────────────────

    public function getPaymentMethods(): array
    {
        return Cache::remember('whmcs.payment_methods', 3600, function () {
            return $this->client->callSafe('GetPaymentMethods');
        });
    }

    /**
     * Get gateway module configuration (API keys, secrets, etc.) from WHMCS.
     * Reads from tblpaymentgateways via the orcus_sso.php proxy.
     * Cached for 1 hour — gateway settings rarely change.
     *
     * @param string $gatewayModule  e.g. 'stripe', 'sslcommerz'
     * @return array  ['result' => 'success', 'settings' => [...]] or error
     */
    public function getGatewayConfig(string $gatewayModule): array
    {
        return Cache::remember("whmcs.gateway_config.{$gatewayModule}", 3600, function () use ($gatewayModule) {
            return $this->client->callSsoProxySafe('GetGatewayConfig', [
                'gateway' => $gatewayModule,
            ]);
        });
    }

    /**
     * Add credit to a client's account (Add Funds).
     * Creates an invoice for the credit amount that the client can pay.
     */
    public function addCredit(int $clientId, float $amount, string $paymentMethod = ''): array
    {
        $params = [
            'userid'           => $clientId,
            'status'           => 'Unpaid',
            'sendinvoice'      => '1',
            'itemdescription1' => 'Add Funds',
            'itemamount1'      => number_format($amount, 2, '.', ''),
            'itemtaxed1'       => '0',
            'autoapplycredit'  => '0',
        ];
        if ($paymentMethod) {
            $params['paymentmethod'] = $paymentMethod;
        }
        return $this->client->call('CreateInvoice', $params);
    }

    /**
     * Apply existing credit balance to an invoice.
     */
    public function applyCredit(int $invoiceId, float $amount): array
    {
        return $this->client->call('ApplyCredit', [
            'invoiceid' => $invoiceId,
            'amount'    => $amount,
        ]);
    }

    /**
     * Record a payment against an invoice (e.g. after Stripe/PayPal capture).
     */
    public function addInvoicePayment(int $invoiceId, string $transId, float $amount, string $gateway, ?float $fees = null): array
    {
        $params = [
            'invoiceid' => $invoiceId,
            'transid'   => $transId,
            'amount'    => $amount,
            'gateway'   => $gateway,
        ];
        if ($fees !== null) {
            $params['fees'] = $fees;
        }
        return $this->client->call('AddInvoicePayment', $params);
    }

    /**
     * Update the payment method on an invoice.
     */
    public function updateInvoicePaymentMethod(int $invoiceId, string $paymentMethod): array
    {
        return $this->client->call('UpdateInvoice', [
            'invoiceid'     => $invoiceId,
            'paymentmethod' => $paymentMethod,
        ]);
    }

    /**
     * Get credit log entries for a client.
     */
    public function getCredits(int $clientId): array
    {
        return $this->client->callSafe('GetCredits', [
            'clientid' => $clientId,
        ]);
    }

    // ─── TLD Pricing ───────────────────────────────────────

    public function getTLDPricing(?int $currencyId = null): array
    {
        $params = [];
        if ($currencyId) {
            $params['currencyid'] = $currencyId;
        }
        return Cache::remember("whmcs.tld_pricing.{$currencyId}", 3600, function () use ($params) {
            return $this->client->callSafe('GetTLDPricing', $params);
        });
    }

    // ─── Domain Availability ───────────────────────────────

    public function domainCheck(string $domain): array
    {
        $parts = explode('.', $domain, 2);
        return $this->client->callSafe('DomainWhois', [
            'domain' => $domain,
        ]);
    }
}
