<?php

namespace Tests\Unit;

use App\Services\SimilarityService;
use PHPUnit\Framework\TestCase;

class SimilarityExplainTest extends TestCase
{
    private array $bluebook = [
        'title'    => 'Development of a Web-Based Attendance Monitoring System Using QR Codes',
        'keywords' => ['Attendance', 'QR Code', 'Web System'],
        'abstract' => 'This study developed a web-based system that records student attendance through QR code scanning.',
        'ocrText'  => 'The proponents built a Laravel web application that scans QR codes to log attendance for each class session.',
    ];

    public function test_the_breakdown_adds_up_to_the_score(): void
    {
        $title    = 'QR Code Attendance Monitoring for Classrooms';
        $keywords = ['Attendance', 'QR Code'];
        $abstract = 'Students scan a QR code to record attendance.';

        $score = SimilarityService::computeSimilarity($title, $keywords, $abstract, $this->bluebook);
        $why   = SimilarityService::explain($title, $keywords, $abstract, $this->bluebook);

        $this->assertEqualsWithDelta($score * 100, array_sum(array_column($why['fields'], 'points')), 0.3);
        $this->assertStringContainsString('<mark>Attendance</mark>', $why['book']['title']);
        $this->assertStringContainsString('<mark>Monitoring</mark>', $why['proposal']['title']);
        $this->assertStringNotContainsString('<mark>Development</mark>', $why['book']['title']);
        $this->assertContains('attendance', $why['fields'][0]['words']);
    }

    public function test_the_upload_breakdown_adds_up_and_quotes_both_documents(): void
    {
        $proposal = 'This capstone proposes a web-based attendance monitoring system using QR codes. '
            . 'Students scan a QR code to record their attendance in each class session.';

        $score = SimilarityService::computeSimilarityFromText($proposal, $this->bluebook);
        $why   = SimilarityService::explainFromText($proposal, $this->bluebook);

        $this->assertEqualsWithDelta($score * 100, array_sum(array_column($why['fields'], 'points')), 0.3);
        $this->assertNotEmpty($why['excerpts']);
        $this->assertNotEmpty($why['proposalExcerpts']);
        $this->assertStringContainsString('<mark>attendance</mark>', $why['excerpts'][0]);
    }

    public function test_highlighting_escapes_the_text(): void
    {
        $html = SimilarityService::highlight('<script>attendance</script> & more', ['attendance']);

        $this->assertSame('&lt;script&gt;<mark>attendance</mark>&lt;/script&gt; &amp; more', $html);
    }
}
