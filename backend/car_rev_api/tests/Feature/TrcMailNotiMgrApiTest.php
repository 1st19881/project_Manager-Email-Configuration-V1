<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrcMailNotiMgrApiTest extends TestCase
{
    public function test_import_template_can_be_downloaded(): void
    {
        $response = $this->get('/api/v1/mail-notification-managers-import/template');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_import_requires_a_file(): void
    {
        $this->postJson('/api/v1/mail-notification-managers-import')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_empty_payload_returns_422_with_required_fields(): void
    {
        $response = $this->postJson('/api/v1/mail-notification-managers', []);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_code', 'plant', 'email']);
    }

    public function test_invalid_list_filters_return_422(): void
    {
        $response = $this->getJson('/api/v1/mail-notification-managers?active_flag=X&per_page=101');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['active_flag', 'per_page']);
    }
}
