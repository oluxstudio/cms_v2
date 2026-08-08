<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Invoice;
use App\Models\Media;
use App\Models\Page;
use App\Models\SiteActivityLog;
use App\Models\Todo;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    /**
     * Record a site activity.
     *
     * @param  string  $entityType  page | form | form_response | todo | media | member | component
     * @param  string  $action  created | updated | published | unpublished | deleted | responded | completed | joined | uploaded
     * @param  string  $title  Human-readable headline
     * @param  array{
     *   entity_id?:   int|null,
     *   description?: string|null,
     *   url?:         string|null,   relative to site slug e.g. "/pages" or "/forms/5/responses"
     *   icon?:        string|null,
     *   user_id?:     int|null,      override authenticated user
     *   meta?:        array|null,
     * } $options
     */
    public static function log(
        string $siteId,
        string $entityType,
        string $action,
        string $title,
        array $options = []
    ): SiteActivityLog {
        return SiteActivityLog::create([
            'site_id' => $siteId,
            'user_id' => $options['user_id'] ?? Auth::id(),
            'entity_type' => $entityType,
            'entity_id' => $options['entity_id'] ?? null,
            'action' => $action,
            'title' => $title,
            'description' => $options['description'] ?? null,
            'url' => $options['url'] ?? null,
            'icon' => $options['icon'] ?? $entityType,
            'meta' => $options['meta'] ?? null,
        ]);
    }

    // ─── Convenience shorthands ───────────────────────────────────

    public static function pageCreated(Page $page): void
    {
        static::log(
            $page->site_id,
            'page',
            'created',
            "Page \"{$page->name}\" was created",
            [
                'entity_id' => $page->id,
                'description' => $page->url ? "URL: {$page->url}" : null,
                'url' => '/pages',
                'meta' => ['page_url' => $page->url, 'is_published' => $page->is_published],
            ]
        );
    }

    public static function pagePublished(Page $page): void
    {
        static::log(
            $page->site_id,
            'page',
            'published',
            "Page \"{$page->name}\" was published",
            [
                'entity_id' => $page->id,
                'description' => $page->url ? "Live at: {$page->url}" : null,
                'url' => '/pages',
                'meta' => ['page_url' => $page->url],
            ]
        );
    }

    public static function formCreated(Form $form): void
    {
        static::log(
            $form->site_id,
            'form',
            'created',
            "Form \"{$form->displayTitle()}\" was created",
            [
                'entity_id' => $form->id,
                'description' => $form->description,
                'url' => '/forms',
                'meta' => ['form_name' => $form->name],
            ]
        );
    }

    public static function formResponse(FormResponse $response): void
    {
        $form = $response->form;
        $contactData = $response->extractContactData();
        $who = $contactData['name'] !== 'Unknown' ? $contactData['name'] : ($contactData['email'] ?? 'Someone');

        static::log(
            $form->site_id,
            'form_response',
            'responded',
            "New response on \"{$form->displayTitle()}\"",
            [
                'entity_id' => $response->id,
                'description' => "{$who} submitted the form",
                'url' => "/forms/{$form->id}/responses",
                'icon' => 'response',
                'meta' => [
                    'form_id' => $form->id,
                    'form_name' => $form->displayTitle(),
                    'submitter' => $who,
                    'email' => $contactData['email'],
                ],
            ]
        );
    }

    public static function todoCreated(Todo $todo): void
    {
        static::log(
            $todo->site_id,
            'todo',
            'created',
            "Task \"{$todo->title}\" was added",
            [
                'entity_id' => $todo->id,
                'description' => $todo->description,
                'url' => '/todos',
                'meta' => ['priority' => $todo->priority, 'status' => $todo->status],
            ]
        );
    }

    public static function todoCompleted(Todo $todo): void
    {
        static::log(
            $todo->site_id,
            'todo',
            'completed',
            "Task \"{$todo->title}\" was completed",
            [
                'entity_id' => $todo->id,
                'description' => null,
                'url' => '/todos',
                'meta' => ['priority' => $todo->priority],
            ]
        );
    }

    public static function mediaUploaded(Media $media): void
    {
        static::log(
            $media->site_id,
            'media',
            'uploaded',
            "Media \"{$media->name}\" was uploaded",
            [
                'entity_id' => $media->id,
                'description' => $media->mime_type ?? null,
                'url' => '/media',
                'meta' => ['mime_type' => $media->mime_type ?? null, 'size' => $media->size ?? null],
            ]
        );
    }

    /** Booking lifecycle: created | confirmed | cancelled. */
    public static function bookingEvent(Booking $booking, string $action): void
    {
        $verb = ['created' => 'was made', 'confirmed' => 'was confirmed', 'cancelled' => 'was cancelled'][$action] ?? $action;
        static::log(
            $booking->site_id,
            'booking',
            $action,
            "Booking {$booking->reference} {$verb}",
            [
                'entity_id' => $booking->id,
                'description' => trim(($booking->customer_name ?? 'Customer').' · '.($booking->service?->name ?? 'Service')
                    .($booking->starts_at ? ' · '.$booking->starts_at->format('D, M j · g:i A') : '')),
                'url' => '/bookings',
                'meta' => ['reference' => $booking->reference, 'email' => $booking->customer_email],
            ]
        );
    }

    /** Invoice lifecycle: created (drafted) | sent. */
    public static function invoiceEvent(Invoice $invoice, string $action): void
    {
        $verb = ['created' => 'was drafted', 'sent' => 'was sent'][$action] ?? $action;
        static::log(
            $invoice->site_id,
            'invoice',
            $action,
            "Invoice {$invoice->number} {$verb}",
            [
                'entity_id' => $invoice->id,
                'description' => trim(($invoice->customer_name ?? 'Client').' · '.Money::format((int) $invoice->total_cents, $invoice->currency)),
                'url' => '/invoices',
                'meta' => ['number' => $invoice->number, 'email' => $invoice->customer_email],
            ]
        );
    }

    public static function memberJoined(User $user, string $siteId): void
    {
        static::log(
            $siteId,
            'member',
            'joined',
            "{$user->name} joined the team",
            [
                'entity_id' => $user->id,
                'description' => $user->email,
                'url' => '/team',
                'meta' => ['user_email' => $user->email],
            ]
        );
    }
}
