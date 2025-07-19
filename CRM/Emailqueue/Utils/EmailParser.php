<?php

/**
 * Advanced MIME Email Parser - Handles nested multipart structures
 */
class CRM_Emailqueue_Utils_EmailParser {

  private $boundaries = [];
  private $result = [
    'text_parts' => [],
    'html_parts' => [],
    'attachments' => [],
    'total_parts' => 0,
    'boundaries' => [],
    'structure' => []
  ];

  /**
   * Main parsing function
   */
  public function parse($emailBody) {
    $this->result = [
      'text_parts' => [],
      'html_parts' => [],
      'attachments' => [],
      'total_parts' => 0,
      'boundaries' => [],
      'structure' => []
    ];
    //echo '<pre>'; print_r($emailBody); echo '</pre>';exit;
    // Find all boundaries in the email
    $this->boundaries = $this->findAllBoundaries($emailBody);
    $this->result['boundaries'] = $this->boundaries;
    //echo '<pre>'; print_r($this->boundaries); echo '</pre>';
    if (empty($this->boundaries)) {
      // Simple email without MIME structure
      $this->result['text_parts'][] = [
        'type' => 'text',
        'content' => $emailBody,
        'encoding' => '',
        'charset' => 'utf-8',
        'size' => strlen($emailBody),
        'headers' => []
      ];
      $this->result['total_parts'] = 1;
      return $this->result;
    }

    // Parse with the primary boundary
    // Parse with the primary boundary
    if (count($this->boundaries) >= 4) {
      $primaryBoundary = $this->boundaries[0];
    }
    else {
      $primaryBoundary = $this->boundaries[1];
    }

    $this->parseMultipartContent($emailBody, $primaryBoundary, 0);

    return $this->result;
  }

  /**
   * Find all boundaries in the email content
   */
  private function findAllBoundaries($content) {
    $boundaries = [];

    // Pattern to match boundary declarations and usage
    $patterns = [
      '/boundary=(["\']?)([^"\'\s;]+)\1/i',  // From Content-Type headers
      '/--([a-zA-Z0-9_=\-\.]+)/m'           // From actual boundary usage
    ];

    foreach ($patterns as $pattern) {
      if (preg_match_all($pattern, $content, $matches)) {
        foreach ($matches[count($matches) - 1] as $boundary) {
          $boundary = trim($boundary, '"\'');
          if (!in_array($boundary, $boundaries) && !empty($boundary)) {
            // Verify this boundary actually exists in content
            if (strpos($content, '--' . $boundary) !== FALSE) {
              $boundaries[] = $boundary;
            }
          }
        }
      }
    }

    // Sort boundaries by length (longer first to avoid partial matches)
    usort($boundaries, function ($a, $b) {
      return strlen($b) - strlen($a);
    });

    return array_unique($boundaries);
  }

  /**
   * Parse multipart content recursively
   */
  private function parseMultipartContent($content, $boundary, $depth = 0) {
    $parts = $this->splitByBoundary($content, $boundary);

    foreach ($parts as $partIndex => $part) {
      $part = trim($part);
      if (empty($part) || $part === '--') {
        continue;
      }

      $parsedPart = $this->parseIndividualPart($part, $depth);

      if ($parsedPart) {
        $this->result['total_parts']++;

        // Check if this part is itself multipart
        if ($this->isMultipartContent($parsedPart)) {
          $nestedBoundary = $this->extractBoundaryFromPart($parsedPart);
          if ($nestedBoundary && in_array($nestedBoundary, $this->boundaries)) {
            // Recursively parse nested multipart
            $this->parseMultipartContent($parsedPart['content'], $nestedBoundary, $depth + 1);
            continue;
          }
        }

        // Add to appropriate category
        switch ($parsedPart['type']) {
          case 'text':
            $this->result['text_parts'][] = $parsedPart;
            break;
          case 'html':
            $this->result['html_parts'][] = $parsedPart;
            break;
          case 'attachment':
            $this->result['attachments'][] = $parsedPart;
            break;
        }

        // Add to structure for debugging
        $this->result['structure'][] = [
          'depth' => $depth,
          'type' => $parsedPart['type'],
          'boundary' => $boundary,
          'content_type' => $parsedPart['content_type'] ?? '',
          'size' => $parsedPart['size']
        ];
      }
    }
  }

  /**
   * Split content by boundary
   */
  private function splitByBoundary($content, $boundary) {
    $pattern = '/--' . preg_quote($boundary, '/') . '(?:--)?/';
    $parts = preg_split($pattern, $content);

    // Remove empty parts
    return array_filter($parts, function ($part) {
      return !empty(trim($part));
    });
  }

  /**
   * Parse individual MIME part
   */
  private function parseIndividualPart($part, $depth = 0) {
    // Split headers and content
    $headerContentSplit = preg_split('/\r?\n\r?\n/', $part, 2);

    if (count($headerContentSplit) < 2) {
      // No clear header/content separation, treat as content only
      return [
        'type' => 'text',
        'content' => $part,
        'headers' => [],
        'encoding' => '',
        'charset' => 'utf-8',
        'size' => strlen($part),
        'depth' => $depth
      ];
    }

    $headers = $this->parseHeaders($headerContentSplit[0]);
    $content = $headerContentSplit[1];

    // Extract key information from headers
    $contentType = strtolower($headers['content-type'] ?? '');
    $contentDisposition = strtolower($headers['content-disposition'] ?? '');
    $transferEncoding = strtolower($headers['content-transfer-encoding'] ?? '');

    // Decode content
    $decodedContent = $this->decodeContent($content, $transferEncoding);

    $partInfo = [
      'headers' => $headers,
      'content' => $decodedContent,
      'raw_content' => $content,
      'encoding' => $transferEncoding,
      'charset' => $this->extractCharset($contentType),
      'size' => strlen($decodedContent),
      'depth' => $depth,
      'content_type' => $contentType
    ];

    // Determine part type
    if (strpos($contentDisposition, 'attachment') !== FALSE) {
      $partInfo['type'] = 'attachment';
      $partInfo['filename'] = $this->extractFilename($contentDisposition, $contentType);
      $partInfo['mime_type'] = $this->extractMimeType($contentType);
    }
    elseif (strpos($contentType, 'multipart/') !== FALSE) {
      $partInfo['type'] = 'multipart';
      $partInfo['multipart_type'] = $this->extractMimeType($contentType);
    }
    elseif (strpos($contentType, 'text/html') !== FALSE) {
      $partInfo['type'] = 'html';
    }
    elseif (strpos($contentType, 'text/plain') !== FALSE) {
      $partInfo['type'] = 'text';
    }
    else {
      // Unknown content type, check if it might be an attachment
      if (!empty($contentType) && $contentType !== 'text/plain') {
        $partInfo['type'] = 'attachment';
        $partInfo['filename'] = 'unknown_attachment';
        $partInfo['mime_type'] = $this->extractMimeType($contentType);
      }
      else {
        $partInfo['type'] = 'text';
      }
    }

    return $partInfo;
  }

  /**
   * Check if content is multipart
   */
  private function isMultipartContent($parsedPart) {
    return isset($parsedPart['type']) && $parsedPart['type'] === 'multipart';
  }

  /**
   * Extract boundary from a multipart part
   */
  private function extractBoundaryFromPart($parsedPart) {
    $contentType = $parsedPart['content_type'] ?? '';
    if (preg_match('/boundary=(["\']?)([^"\'\s;]+)\1/i', $contentType, $matches)) {
      return trim($matches[2], '"\'');
    }
    return NULL;
  }

  /**
   * Parse headers
   */
  private function parseHeaders($headerString) {
    $headers = [];
    $lines = preg_split('/\r?\n/', trim($headerString));

    $currentHeader = '';
    foreach ($lines as $line) {
      if (preg_match('/^([^:]+):\s*(.*)$/', $line, $matches)) {
        $currentHeader = strtolower(trim($matches[1]));
        $headers[$currentHeader] = trim($matches[2]);
      }
      elseif ($currentHeader && preg_match('/^\s+(.*)/', $line, $matches)) {
        $headers[$currentHeader] .= ' ' . trim($matches[1]);
      }
    }

    return $headers;
  }

  /**
   * Extract charset from content-type
   */
  private function extractCharset($contentType) {
    if (preg_match('/charset=([^;\s]+)/i', $contentType, $matches)) {
      return trim($matches[1], '"\'');
    }
    return 'utf-8';
  }

  /**
   * Extract MIME type
   */
  private function extractMimeType($contentType) {
    if (preg_match('/^([^;]+)/', $contentType, $matches)) {
      return trim($matches[1]);
    }
    return $contentType;
  }

  /**
   * Extract filename
   */
  private function extractFilename($contentDisposition, $contentType = '') {
    // Try Content-Disposition first
    if (preg_match('/filename[*]?=([^;]+)/i', $contentDisposition, $matches)) {
      return trim($matches[1], '"\'');
    }

    // Try Content-Type name parameter
    if (preg_match('/name=([^;]+)/i', $contentType, $matches)) {
      return trim($matches[1], '"\'');
    }

    return 'unknown_attachment';
  }

  /**
   * Decode content based on transfer encoding
   */
  private function decodeContent($content, $encoding) {
    switch (strtolower($encoding)) {
      case 'base64':
        return base64_decode($content);
      case 'quoted-printable':
        return quoted_printable_decode($content);
      case '7bit':
      case '8bit':
      default:
        return $content;
    }
  }
}

/**
 * Simple function interface for backward compatibility
 */
function parseMimeBody($emailBody) {
  $parser = new MimeParser();
  return $parser->parse($emailBody);
}

/**
 * Display parsed results
 */
function displayAdvancedResults($result) {
  echo "=== ADVANCED MIME PARSING RESULTS ===\n";
  echo "Boundaries Found: " . count($result['boundaries']) . "\n";
  foreach ($result['boundaries'] as $i => $boundary) {
    echo "  Boundary " . ($i + 1) . ": " . $boundary . "\n";
  }
  echo "Total Parts: " . $result['total_parts'] . "\n\n";

  echo "STRUCTURE:\n";
  foreach ($result['structure'] as $i => $struct) {
    $indent = str_repeat("  ", $struct['depth']);
    echo $indent . "Part " . ($i + 1) . ": " . $struct['type'] .
      " (" . $struct['content_type'] . ") - " . $struct['size'] . " bytes\n";
  }
  echo "\n";

  echo "TEXT PARTS (" . count($result['text_parts']) . "):\n";
  foreach ($result['text_parts'] as $index => $part) {
    echo "  Part " . ($index + 1) . " (Depth: " . $part['depth'] . "):\n";
    echo "    Encoding: " . $part['encoding'] . "\n";
    echo "    Charset: " . $part['charset'] . "\n";
    echo "    Size: " . $part['size'] . " bytes\n";
    echo "    Content: " . substr($part['content'], 0, 100) . "...\n\n";
  }

  echo "HTML PARTS (" . count($result['html_parts']) . "):\n";
  foreach ($result['html_parts'] as $index => $part) {
    echo "  Part " . ($index + 1) . " (Depth: " . $part['depth'] . "):\n";
    echo "    Encoding: " . $part['encoding'] . "\n";
    echo "    Charset: " . $part['charset'] . "\n";
    echo "    Size: " . $part['size'] . " bytes\n";
    echo "    Content: " . substr($part['content'], 0, 100) . "...\n\n";
  }

  echo "ATTACHMENTS (" . count($result['attachments']) . "):\n";
  foreach ($result['attachments'] as $index => $attachment) {
    echo "  Attachment " . ($index + 1) . " (Depth: " . $attachment['depth'] . "):\n";
    echo "    Filename: " . ($attachment['filename'] ?? 'N/A') . "\n";
    echo "    MIME Type: " . ($attachment['mime_type'] ?? 'N/A') . "\n";
    echo "    Encoding: " . $attachment['encoding'] . "\n";
    echo "    Size: " . $attachment['size'] . " bytes\n\n";
  }
}

/*

// ===== TEST CASES =====

echo "=== TEST CASE 1: Simple Email (Single Boundary) ===\n";
$simpleEmail = "--=_75d2b316d134381b4dd5615d8a955248Content-Transfer-Encoding: 8bitContent-Type: text/plain; charset=utf-8test single emailtest single emailtest single emailtest single emailtest single email--=_75d2b316d134381b4dd5615d8a955248Content-Transfer-Encoding: 8bitContent-Type: text/html; charset=utf-8<p>test single emailtest single emailtest single emailtest single emailtest single email</p>--=_75d2b316d134381b4dd5615d8a955248--";

$result1 = parseMimeBody($simpleEmail);
displayAdvancedResults($result1);

echo "\n" . str_repeat("=", 60) . "\n\n";

echo "=== TEST CASE 2: Email with Attachments (Nested Boundaries) ===\n";
$complexEmail = "--outer_boundaryContent-Type: multipart/alternative; boundary=\"inner_boundary\"

--inner_boundaryContent-Type: text/plain; charset=utf-8

This is the plain text version of the email.
Multiple lines of text here.

--inner_boundaryContent-Type: text/html; charset=utf-8

<html>
<body>
<h1>This is the HTML version</h1>
<p>With <strong>formatting</strong> and styles.</p>
</body>
</html>

--inner_boundary--

--outer_boundaryContent-Type: application/pdf; name=\"document.pdf\"Content-Transfer-Encoding: base64Content-Disposition: attachment; filename=\"report.pdf\"

JVBERi0xLjQKJdPr6eEKMSAwIG9iago8PAovVGl0bGUgKEV4YW1wbGUgUERGKQovQ3JlYXRvciAoUEhQ
KQo+PgplbmRvYmoKMiAwIG9iago8PAovVHlwZSAvQ2F0YWxvZwovUGFnZXMgMyAwIFIKPj4KZW5kb2Jq

--outer_boundaryContent-Type: image/jpeg; name=\"photo.jpg\"Content-Transfer-Encoding: base64Content-Disposition: attachment; filename=\"vacation.jpg\"

/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAMCAgMCAgMDAwMEAwMEBQgFBQQEBQoHBwYIDAoMDAsK
CwsNDhIQDQ4RDgsLEBYQERMUFRUVDA8XGBYUGBIUFRT/2wBDAQMEBAUEBQkFBQkUDQsNFBQUFBQU

--outer_boundary--";

$result2 = parseMimeBody($complexEmail);
displayAdvancedResults($result2);

echo "\n" . str_repeat("=", 60) . "\n\n";

echo "=== TEST CASE 3: Real-world Email Structure ===\n";
$realWorldEmail = "Content-Type: multipart/mixed; boundary=\"mixed_boundary_12345\"

--mixed_boundary_12345Content-Type: multipart/alternative; boundary=\"alt_boundary_67890\"

--alt_boundary_67890Content-Type: text/plain; charset=\"UTF-8\"Content-Transfer-Encoding: 8bit

Dear Customer,

Thank you for your purchase. Please find the invoice attached.

Best regards,
Sales Team

--alt_boundary_67890Content-Type: text/html; charset=\"UTF-8\"Content-Transfer-Encoding: 8bit

<html>
<head><title>Purchase Confirmation</title></head>
<body>
<h2>Dear Customer,</h2>
<p>Thank you for your <strong>purchase</strong>. Please find the invoice attached.</p>
<p>Best regards,<br/>Sales Team</p>
</body>
</html>

--alt_boundary_67890--

--mixed_boundary_12345Content-Type: application/pdf; name=\"invoice.pdf\"Content-Transfer-Encoding: base64Content-Disposition: attachment; filename=\"invoice_001.pdf\"

JVBERi0xLjQKJdPr6eEKMSAwIG9iago8PAovVGl0bGUgKEludm9pY2UgIzAwMSk=

--mixed_boundary_12345--";

$result3 = parseMimeBody($realWorldEmail);
displayAdvancedResults($result3);

// Helper function to get content safely
function getContent($result, $type, $index = 0) {
  $key = $type . '_parts';
  if (isset($result[$key][$index])) {
    return $result[$key][$index]['content'];
  }
  return NULL;
}

echo "\n=== ACCESSING CONTENT ===\n";
echo "Text content: " . substr(getContent($result3, 'text') ?? 'None', 0, 50) . "...\n";
echo "HTML content: " . substr(getContent($result3, 'html') ?? 'None', 0, 50) . "...\n";
echo "Attachments: " . count($result3['attachments']) . "\n";

*/
