<?php
// OCA\TeamFolders\Service\FolderService.php

/**
 * Fix XML parsing error when group names contain special characters like &
 * 
 * @param string $groupName
 * @return string
 */
private function sanitizeGroupNameForXml(string $groupName): string {
    // Escape special XML characters to prevent xmlParseEntityRef errors
    return htmlspecialchars($groupName, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/**
 * Override the original method to sanitize group names before XML generation
 * 
 * @param Folder $folder
 * @param array $permissions
 * @return string
 */
public function generateSharingSidebarXml(Folder $folder, array $permissions): string {
    $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><sharing></sharing>');
    
    foreach ($permissions as $permission) {
        $groupElement = $xml->addChild('group');
        
        // Sanitize group name to prevent XML parsing errors
        $sanitizedName = $this->sanitizeGroupNameForXml($permission['group_name']);
        $groupElement->addChild('name', $sanitizedName);
        
        $groupElement->addChild('permissions', (string)$permission['permissions']);
        
        if (isset($permission['display_name'])) {
            $sanitizedDisplayName = $this->sanitizeGroupNameForXml($permission['display_name']);
            $groupElement->addChild('display_name', $sanitizedDisplayName);
        }
    }
    
    return $xml->asXML();
}

/**
 * Hook into the existing method to apply sanitization
 * 
 * @param string $xmlString
 * @return string
 */
public function sanitizeExistingXml(string $xmlString): string {
    // Replace & in group names that are not already escaped
    $pattern = '/<name>([^<]*&[^<]*)<\/name>/i';
    $callback = function($matches) {
        $sanitized = htmlspecialchars($matches[1], ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return '<name>' . $sanitized . '</name>';
    };
    
    return preg_replace_callback($pattern, $callback, $xmlString);
}

/**
 * Main method to handle the sharing sidebar with proper XML escaping
 * 
 * @param Folder $folder
 * @param array $groupPermissions
 * @return string
 */
public function getSharingSidebarContent(Folder $folder, array $groupPermissions): string {
    try {
        // Generate XML with sanitized names
        $xmlContent = $this->generateSharingSidebarXml($folder, $groupPermissions);
        
        // Validate the generated XML
        $dom = new \DOMDocument();
        $dom->loadXML($xmlContent);
        
        if (!$dom->validate()) {
            // Fallback: apply additional sanitization if validation fails
            $xmlContent = $this->sanitizeExistingXml($xmlContent);
        }
        
        return $xmlContent;
        
    } catch (\Exception $e) {
        // Log error and return empty or fallback content
        \OC::$server->getLogger()->error(
            'Failed to generate sharing sidebar XML: ' . $e->getMessage(),
            ['app' => 'teamfolders']
        );
        
        return '<?xml version="1.0" encoding="UTF-8"?><sharing></sharing>';
    }
}
