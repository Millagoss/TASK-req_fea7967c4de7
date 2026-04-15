<?php

namespace Tests\Unit;

use App\Services\FieldMaskingService;
use Tests\TestCase;

class FieldMaskingServiceTest extends TestCase
{
    public function test_mask_shows_first_2_and_last_2_chars_for_long_strings(): void
    {
        // "secretvalue" = 11 chars => "se*******ue"
        $result = FieldMaskingService::mask('secretvalue');
        $this->assertEquals('se' . str_repeat('*', 7) . 'ue', $result);
        $this->assertEquals(11, mb_strlen($result));
    }

    public function test_mask_fully_masks_short_strings(): void
    {
        $this->assertEquals('***', FieldMaskingService::mask('abc'));
        $this->assertEquals('*****', FieldMaskingService::mask('hello'));
        $this->assertEquals('*', FieldMaskingService::mask('x'));
        $this->assertEquals('', FieldMaskingService::mask(''));
    }

    public function test_mask_boundary_at_6_chars(): void
    {
        // 5 chars => fully masked
        $this->assertEquals('*****', FieldMaskingService::mask('abcde'));
        // 6 chars => first 2 + 2 masked + last 2
        $result = FieldMaskingService::mask('abcdef');
        $this->assertEquals('ab**ef', $result);
    }

    public function test_mask_array_masks_sensitive_field_names(): void
    {
        $data = [
            'username'   => 'john',
            'password'   => 'supersecretpw',
            'ip_address' => '192.168.1.100',
        ];

        $masked = FieldMaskingService::maskArray($data);

        $this->assertEquals('john', $masked['username']); // not sensitive
        $this->assertNotEquals('supersecretpw', $masked['password']);
        $this->assertEquals('su*********pw', $masked['password']);
        $this->assertNotEquals('192.168.1.100', $masked['ip_address']);
    }

    public function test_mask_array_preserves_non_sensitive_fields(): void
    {
        $data = [
            'name'         => 'John Doe',
            'email'        => 'john@example.com',
            'display_name' => 'JD',
        ];

        $masked = FieldMaskingService::maskArray($data);

        $this->assertEquals($data, $masked);
    }

    public function test_mask_array_handles_nested_arrays(): void
    {
        $data = [
            'user' => [
                'name'     => 'John',
                'password' => 'secretpassword',
            ],
            'meta' => [
                'deep' => [
                    'identifier' => 'ID-12345678',
                ],
            ],
        ];

        $masked = FieldMaskingService::maskArray($data);

        $this->assertEquals('John', $masked['user']['name']);
        $this->assertNotEquals('secretpassword', $masked['user']['password']);
        $this->assertNotEquals('ID-12345678', $masked['meta']['deep']['identifier']);
    }

    public function test_all_sensitive_fields_are_masked(): void
    {
        $data = [];
        foreach (FieldMaskingService::SENSITIVE_FIELDS as $field) {
            $data[$field] = 'sensitive_value_here';
        }

        $masked = FieldMaskingService::maskArray($data);

        foreach (FieldMaskingService::SENSITIVE_FIELDS as $field) {
            $this->assertNotEquals('sensitive_value_here', $masked[$field],
                "Field '{$field}' should be masked but was not.");
            $this->assertStringContainsString('*', $masked[$field]);
        }
    }

    public function test_sensitive_fields_constant_contains_expected_fields(): void
    {
        $expected = ['password', 'password_hash', 'service_credential_hash', 'ip_address', 'identifier'];
        $this->assertEquals($expected, FieldMaskingService::SENSITIVE_FIELDS);
    }
}
