<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\Admin;

use YouWillLikeIT\Cleaning\Services\BulkNotificationService;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;

/**
 * CMS panel for sending bulk SMS / WhatsApp notifications to worker groups.
 *
 * Visible to: COMPANY_ADMIN, SUPER_ADMIN.
 *
 * URL: /admin/bulk-notify/
 */
class BulkNotificationAdmin extends ModelAdmin
{
    private static string $url_segment   = 'bulk-notify';
    private static string $menu_title    = 'Massenbenachrichtigung';
    private static string $menu_icon_class = 'font-icon-bell';

    /** We don't manage models — this admin is form-only. */
    private static array $managed_models = [];

    // ── Permissions ───────────────────────────────────────────────────────

    public function canView(mixed $member = null): bool
    {
        return Permission::checkMember($member, ['COMPANY_ADMIN', 'SUPER_ADMIN', 'ADMIN']);
    }

    // ── Form ──────────────────────────────────────────────────────────────

    public function getEditForm(mixed $id = null, mixed $fields = null): Form
    {
        // Recipient groups
        $groupOptions = [
            'all_workers'   => 'Alle Mitarbeiter',
            'no_assignment' => 'Mitarbeiter ohne Einsatz heute',
        ];

        // Add individual SS groups
        foreach (Group::get() as $group) {
            $groupOptions['group_' . $group->ID] = 'Gruppe: ' . $group->Title;
        }

        $fields = FieldList::create(
            DropdownField::create(
                'RecipientGroup',
                'Empfänger',
                $groupOptions
            )->setEmptyString('-- Bitte wählen --'),

            DropdownField::create(
                'Channel',
                'Kanal',
                [
                    'whatsapp' => 'WhatsApp (bevorzugt)',
                    'sms'      => 'SMS',
                    'auto'     => 'Automatisch (WhatsApp → SMS)',
                ]
            )->setValue('auto'),

            TextareaField::create('Message', 'Nachricht')
                ->setRows(5)
                ->setDescription('Max. 1600 Zeichen. Keine HTML-Tags.')
        );

        $actions = FieldList::create(
            FormAction::create('doSendBulk', 'Nachricht senden')
                ->setUseButtonTag(true)
                ->addExtraClass('btn-primary btn-main-action')
        );

        return Form::create($this, 'EditForm', $fields, $actions, RequiredFields::create('RecipientGroup', 'Message'));
    }

    /**
     * Handle bulk send form submission.
     */
    public function doSendBulk(array $data, Form $form): HTTPResponse
    {
        $message = substr(strip_tags($data['Message'] ?? ''), 0, 1600);
        $channel = $data['Channel'] ?? 'auto';
        $group   = $data['RecipientGroup'] ?? '';

        if (!$message) {
            $form->sessionMessage('Bitte geben Sie eine Nachricht ein.', 'bad');
            return $this->redirectBack();
        }

        $workers = $this->resolveRecipients($group);

        if ($workers->count() === 0) {
            $form->sessionMessage('Keine Empfänger in der gewählten Gruppe.', 'warning');
            return $this->redirectBack();
        }

        $service = BulkNotificationService::create();

        $result = match ($channel) {
            'sms'      => $service->sendBulkSms($workers, $message),
            'whatsapp' => $service->sendBulkWhatsApp($workers, $message),
            default    => $service->notifyCancellation($workers, '', ''), // auto fallback
        };

        // For 'auto' mode with a custom message, re-call properly
        if ($channel === 'auto') {
            $waFrom = getenv('TWILIO_WHATSAPP_FROM') ?: '';
            $result = $waFrom
                ? $service->sendBulkWhatsApp($workers, $message)
                : $service->sendBulkSms($workers, $message);
        }

        $status = sprintf(
            'Gesendet: %d, Fehler: %d. %s',
            $result['sent'],
            $result['failed'],
            implode(' | ', array_slice($result['errors'], 0, 3))
        );

        $type = $result['failed'] === 0 ? 'good' : 'warning';
        $form->sessionMessage($status, $type);

        return $this->redirectBack();
    }

    // ── Private ───────────────────────────────────────────────────────────

    private function resolveRecipients(string $group): \SilverStripe\ORM\DataList
    {
        if ($group === 'all_workers') {
            $workerGroup = Group::get()->filter('Code', 'workers')->first();
            return $workerGroup
                ? $workerGroup->Members()
                : Member::get()->filter('ID', 0);
        }

        if ($group === 'no_assignment') {
            // Workers with no assignment today
            $assignedIDs = \YouWillLikeIT\Cleaning\DataObjects\WorkerAssignment::get()
                ->filter('AssignedDate', date('Y-m-d'))
                ->column('WorkerID');

            $workerGroup = Group::get()->filter('Code', 'workers')->first();
            $allWorkers  = $workerGroup ? $workerGroup->Members() : Member::get()->filter('ID', 0);

            return $assignedIDs
                ? $allWorkers->exclude('ID', $assignedIDs)
                : $allWorkers;
        }

        if (str_starts_with($group, 'group_')) {
            $groupID = (int) substr($group, 6);
            $g       = Group::get()->byID($groupID);
            return $g ? $g->Members() : Member::get()->filter('ID', 0);
        }

        return Member::get()->filter('ID', 0);
    }
}
