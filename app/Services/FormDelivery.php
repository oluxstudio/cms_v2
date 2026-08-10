<?php

namespace App\Services;

use App\Mail\FormSubmissionNotification;
use App\Mail\SubmissionReceipt;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Site;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Dispatches a form submission to its enabled delivery channels.
 *
 * Channels are declared in config/form_channels.php and enabled per form
 * (forms.delivery). Only channels that are BOTH enabled and `implemented`
 * actually send — Email is live today; SMS/WhatsApp are skipped until wired to
 * a provider. All sending is best-effort (a failed channel never blocks the
 * submission response).
 */
class FormDelivery
{
    public function deliver(Form $form, FormResponse $response, array $fields): void
    {
        $site = $form->site;
        $config = $form->deliveryConfig();

        foreach ($config['channels'] as $channel => $settings) {
            if (empty($settings['enabled'])) {
                continue;
            }
            if (! config("form_channels.channels.{$channel}.implemented", false)) {
                Log::info("FormDelivery: channel [{$channel}] enabled but not implemented — skipped.", ['form' => $form->id]);

                continue;
            }

            try {
                match ($channel) {
                    'email' => $this->email($site, $form, $response, $fields, $settings),
                    default => null,
                };
            } catch (\Throwable $e) {
                report($e); // never let a channel failure break the submission
            }
        }
    }

    /** The email channel: a receipt to the visitor + an alert to the admin, per toggles. */
    private function email(Site $site, Form $form, FormResponse $response, array $fields, array $settings): void
    {
        // Visitor receipt — only when the submission captured an email address.
        if (($settings['notify_visitor'] ?? true) && ($visitor = $this->visitorEmail($form, $fields))) {
            try {
                Mail::to($visitor)->send(new SubmissionReceipt(
                    $site,
                    $form->displayTitle().' form',
                    $fields['name'] ?? null,
                    $fields,
                ));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Admin alert — to the configured address, else the site owner.
        if ($settings['notify_admin'] ?? true) {
            $admin = ($settings['admin_address'] ?? null) ?: $site->user?->email;
            if ($admin) {
                try {
                    Mail::to($admin)->send(new FormSubmissionNotification(
                        $site,
                        $form->displayTitle().' form',
                        $fields,
                        route('site.forms.response', [$site->name, $response->id]),
                    ));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }
    }

    /** The visitor's email from the submission: an email-typed field, else an email-named one. */
    private function visitorEmail(Form $form, array $fields): ?string
    {
        foreach ((array) ($form->fields ?? []) as $def) {
            $key = $def['key'] ?? '';
            if (($def['type'] ?? '') === 'email' && filter_var($fields[$key] ?? '', FILTER_VALIDATE_EMAIL)) {
                return $fields[$key];
            }
        }
        foreach ($fields as $key => $value) {
            if (str_contains(strtolower((string) $key), 'email') && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $value;
            }
        }

        return null;
    }
}
