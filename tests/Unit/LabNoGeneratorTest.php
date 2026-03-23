<?php

namespace Tests\Unit;

use App\Models\Lab;
use App\Models\LabSequence;
use App\Services\LabNoGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabNoGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected LabNoGenerator $labNoGenerator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->labNoGenerator = new LabNoGenerator();
    }

    private function createLab(string $suffix = ''): Lab
    {
        $u = $suffix ?: uniqid();

        return Lab::create([
            'name' => 'Test Lab '.$u,
            'slug' => 'test-lab-'.$u,
            'subdomain' => 'tl'.$u,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_generates_lab_number_with_current_year()
    {
        $lab = $this->createLab();
        $result = $this->labNoGenerator->generate(null, null, $lab->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('base', $result);
        $this->assertArrayHasKey('full', $result);
        $this->assertArrayHasKey('sequence', $result);
        $this->assertArrayHasKey('year', $result);

        $currentYear = now()->year;
        $this->assertEquals($currentYear, $result['year']);
        $this->assertStringStartsWith($currentYear.'-', $result['base']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d+$/', $result['base']);
    }

    /** @test */
    public function it_generates_lab_number_with_specific_year()
    {
        $lab = $this->createLab();
        $year = 2024;
        $result = $this->labNoGenerator->generate($year, null, $lab->id);

        $this->assertEquals($year, $result['year']);
        $this->assertStringStartsWith('2024-', $result['base']);
    }

    /** @test */
    public function it_generates_lab_number_with_suffix()
    {
        $lab = $this->createLab();
        $result = $this->labNoGenerator->generateWithSuffix('m', null, $lab->id);

        $this->assertEquals('m', $result['suffix']);
        $this->assertStringEndsWith('m', $result['full']);
        $this->assertStringNotEndsWith('m', $result['base']);
    }

    /** @test */
    public function it_generates_sequential_lab_numbers()
    {
        $lab = $this->createLab();
        $result1 = $this->labNoGenerator->generate(null, null, $lab->id);
        $result2 = $this->labNoGenerator->generate(null, null, $lab->id);

        $this->assertEquals($result1['sequence'] + 1, $result2['sequence']);
        $this->assertEquals($result1['year'], $result2['year']);
    }

    /** @test */
    public function it_generates_independent_sequences_per_lab()
    {
        $labA = $this->createLab('a');
        $labB = $this->createLab('b');
        $year = 2026;

        $a1 = $this->labNoGenerator->generate($year, null, $labA->id);
        $b1 = $this->labNoGenerator->generate($year, null, $labB->id);
        $a2 = $this->labNoGenerator->generate($year, null, $labA->id);

        $this->assertEquals('2026-'.$a1['sequence'], $a1['base']);
        $this->assertEquals('2026-'.$b1['sequence'], $b1['base']);
        $this->assertEquals($a1['sequence'], $b1['sequence'], 'Each lab starts its own counter for the year');
        $this->assertEquals($a1['sequence'] + 1, $a2['sequence']);
    }

    /** @test */
    public function it_parses_year_sequence_format()
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
    public function it_parses_legacy_sequence_year_format()
    {
        $labNo = '7001-2025';
        $result = $this->labNoGenerator->parse($labNo);

        $this->assertEquals(2025, $result['year']);
        $this->assertEquals(7001, $result['sequence']);
        $this->assertEquals('7001-2025', $result['base']);
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
        $this->assertTrue($this->labNoGenerator->isValid('7001-2025'));
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
        $lab = $this->createLab();
        $results = [];
        for ($i = 0; $i < 10; $i++) {
            $results[] = $this->labNoGenerator->generate(null, null, $lab->id);
        }

        $sequences = array_column($results, 'sequence');
        $this->assertEquals(count($sequences), count(array_unique($sequences)));

        sort($sequences);
        for ($i = 1; $i < count($sequences); $i++) {
            $this->assertEquals($sequences[$i - 1] + 1, $sequences[$i]);
        }
    }

    /** @test */
    public function it_gets_next_sequence_for_year()
    {
        $lab = $this->createLab();
        LabSequence::create([
            'lab_id' => $lab->id,
            'year' => 2024,
            'last_sequence' => 7000,
        ]);

        $nextSequence = $this->labNoGenerator->getNextSequenceForYear(2024, $lab->id);
        $this->assertEquals(7001, $nextSequence);

        $nextSequence = $this->labNoGenerator->getNextSequenceForYear(2023, $lab->id);
        $this->assertEquals((int) config('lab.start_sequence', 1), $nextSequence);
    }
}
