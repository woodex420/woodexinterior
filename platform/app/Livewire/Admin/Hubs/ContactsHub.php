<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Hubs;

use App\Livewire\Admin\Crm\CompaniesIndex;
use App\Livewire\Admin\Crm\ContactsIndex;

/**
 * P10.1: the Contacts workflow page — people and the companies they belong to.
 */
final class ContactsHub extends Hub
{
    protected function hub(): array
    {
        return [
            'title' => 'Contacts',
            'description' => 'The studio\'s people and companies — one book, two views.',
            'breadcrumbs' => ['CRM' => null, 'Contacts' => null],
            'tabs' => [
                ['id' => 'people', 'label' => 'People', 'component' => ContactsIndex::class, 'permission' => 'contacts.view'],
                ['id' => 'companies', 'label' => 'Companies', 'component' => CompaniesIndex::class, 'permission' => 'contacts.view'],
            ],
        ];
    }
}
