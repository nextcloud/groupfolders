<?php
// Fix for xmlParseEntityRef: no name when group name contains &
// This patch should be applied to the Team folders app

// In file: lib/Controller/SharingController.php or similar

public function shareFolder($folderId, $shareWith, $shareType) {
    // Sanitize group names that contain special XML characters
    if ($shareType === 'group') {
        // Escape ampersand and other XML special characters
        $shareWith = htmlspecialchars($shareWith, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
    
    // Rest of the sharing logic
    // ...
}

// Alternative fix in the XML generation function
private function generateShareXML($shareData) {
    $xml = new SimpleXMLElement('<share></share>');
    
    foreach ($shareData as $key => $value) {
        // Ensure all values are XML-safe
        $safeValue = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $xml->addChild($key, $safeValue);
    }
    
    return $xml->asXML();
}

// Patch for the specific issue in Advanced Permissions
// In file: lib/Service/ShareService.php

public function createShare($folderId, $shareWith, $shareType, $permissions) {
    // Validate and sanitize input
    if ($shareType === 'group' && strpos($shareWith, '&') !== false) {
        // Log the issue for debugging
        $this->logger->warning('Group name contains ampersand: ' . $shareWith);
        
        // Properly encode the group name for XML
        $shareWith = str_replace(['&', '<', '>', '"', "'"], 
                                ['&amp;', '&lt;', '&gt;', '&quot;', '&apos;'], 
                                $shareWith);
    }
    
    // Proceed with the share creation
    // ...
}

// Complete fix for the XML parsing issue
// In file: lib/Http/XMLResponse.php or similar

public function render() {
    $xmlContent = $this->getXMLContent();
    
    // Ensure the XML is well-formed
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    
    // Load with error handling
    $previous = libxml_use_internal_errors(true);
    if (!$dom->loadXML($xmlContent)) {
        $errors = libxml_get_errors();
        libxml_clear_errors();
        
        // Fix common XML issues
        foreach ($errors as $error) {
            if ($error->code === XML_ERR_NAME_REQUIRED) {
                // This is the xmlParseEntityRef error
                // Re-encode the content properly
                $xmlContent = $this->fixXMLEncoding($xmlContent);
                break;
            }
        }
        
        // Try loading again
        $dom->loadXML($xmlContent);
    }
    libxml_use_internal_errors($previous);
    
    return $dom->saveXML();
}

private function fixXMLEncoding($content) {
    // Find and fix unescaped ampersands in text content
    return preg_replace('/&(?!(?:amp|lt|gt|quot|apos|#\d+|#x[0-9a-fA-F]+);)/', '&amp;', $content);
}
