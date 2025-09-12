<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\LabNoGenerator;
use App\Models\LabSequence;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LabNoGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected $labNoGenerator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->labNoGenerator = new LabNoGenerator();
    }

    /** @test */
    public function it_generates_lab_number_with_current_year()
    {
        $result = $this->labNoGenerator->generate();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('base', $result);
        $this->assertArrayHasKey('full', $result);
        $this->assertArrayHasKey('sequence', $result);
        $this->assertArrayHasKey('year', $result);
        
        $currentYear = now()->year;
        $this->assertEquals($currentYear, $result['year']);
        $this->assertStringStartsWith($currentYear . '-', $result['base']);
    }

    /** @test */
    public function it_generates_lab_number_with_specific_year()
    {
        $year = 2024;
        $result = $this->labNoGenerator->generate($year);
        
        $this->assertEquals($year, $result['year']);
        $this->assertStringStartsWith($year . '-', $result['base']);
    }

    /** @test */
    public function it_generates_lab_number_with_suffix()
    {
        $result = $this->labNoGenerator->generateWithSuffix('m');
        
        $this->assertEquals('m', $result['suffix']);
        $this->assertStringEndsWith('m', $result['full']);
        $this->assertStringNotEndsWith('m', $result['base']);
    }

    /** @test */
    public function it_generates_sequential_lab_numbers()
    {
        $result1 = $this->labNoGenerator->generate();
        $result2 = $this->labNoGenerator->generate();
        
        $this->assertEquals($result1['sequence'] + 1, $result2['sequence']);
        $this->assertEquals($result1['year'], $result2['year']);
    }

    /** @test */
    public function it_parses_lab_number_correctly()
    {
        $labNo = '2025-7001';
        $result = $this->labNoGenerator->parse($labNo);
        
        $this->assertEquals(2025, $result['year']);
        $this->assertEquals(7001, $result['sequence']);
        $this->assertEquals('2025-7001', $result['base']);
        $this->assertEquals('', $result['suffix']);
        $this->assertEquals('2025-7001', $result['full']);
    }

    /** @test */
    public function it_parses_lab_number_with_suffix()
    {
        $labNo = '2025-7001m';
        $result = $this->labNoGenerator->parse($labNo);
        
        $this->assertEquals(2025, $result['year']);
        $this->assertEquals(7001, $result['sequence']);
        $this->assertEquals('2025-7001', $result['base']);
        $this->assertEquals('m', $result['suffix']);
        $this->assertEquals('2025-7001m', $result['full']);
    }

    /** @test */
    public function it_validates_lab_number_format()
    {
        $this->assertTrue($this->labNoGenerator->isValid('2025-7001'));
        $this->assertTrue($this->labNoGenerator->isValid('2025-7001m'));
        $this->assertTrue($this->labNoGenerator->isValid('2025-7001h'));
        $this->assertFalse($this->labNoGenerator->isValid('invalid'));
        $this->assertFalse($this->labNoGenerator->isValid('2025-abc'));
        $this->assertFalse($this->labNoGenerator->isValid('25-7001'));
    }

    /** @test */
    public function it_throws_exception_for_invalid_suffix()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->labNoGenerator->generateWithSuffix('x');
    }

    /** @test */
    public function it_throws_exception_for_invalid_parse()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->labNoGenerator->parse('invalid-format');
    }

    /** @test */
    public function it_handles_concurrent_generation()
    {
        // Simulate concurrent requests by generating multiple lab numbers rapidly
        $results = [];
        for ($i = 0; $i < 10; $i++) {
            $results[] = $this->labNoGenerator->generate();
        }
        
        // All should have unique sequences
        $sequences = array_column($results, 'sequence');
        $this->assertEquals(count($sequences), count(array_unique($sequences)));
        
        // All should be sequential
        sort($sequences);
        for ($i = 1; $i < count($sequences); $i++) {
            $this->assertEquals($sequences[$i-1] + 1, $sequences[$i]);
        }
    }

    /** @test */
    public function it_gets_next_sequence_for_year()
    {
        // Create a sequence record
        LabSequence::create(['year' => 2024, 'last_sequence' => 7000]);
        
        $nextSequence = $this->labNoGenerator->getNextSequenceForYear(2024);
        $this->assertEquals(7001, $nextSequence);
        
        // Test for non-existent year
        $nextSequence = $this->labNoGenerator->getNextSequenceForYear(2023);
        $this->assertEquals(config('lab.start_sequence', 7000) + 1, $nextSequence);
    }
}
