<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Customer;
use App\Support\PhoneFormatter;
use Tests\TestCase;

class PhoneFormatterTest extends TestCase
{
    public function test_it_formats_common_indonesian_mobile_numbers(): void
    {
        $this->assertSame('+62 812-3456-7890', PhoneFormatter::indonesia('0812 3456 7890'));
        $this->assertSame('+62 812-3456-7890', PhoneFormatter::indonesia('+62 0812-3456-7890'));
        $this->assertSame('+62 812-3456-7890', PhoneFormatter::indonesia('62081234567890'));
    }

    public function test_model_accessors_use_the_same_formatter(): void
    {
        $customer = new Customer(['phone' => '081234567890']);
        $company = new Company(['phone' => '+62 0812 3456 7890']);

        $this->assertSame('+62 812-3456-7890', $customer->formatted_phone);
        $this->assertSame('+62 812-3456-7890', $company->formatted_phone);
    }

    public function test_it_keeps_non_indonesian_or_local_numbers_unchanged(): void
    {
        $this->assertSame('7615550101', PhoneFormatter::indonesia('7615550101'));
    }
}
