<?php
//VERSION=0.2
class RoundcubeDB extends SQLite3
{
    function __construct($filename)
    {
        $this->open($filename);
        $this->user = $this->query('SELECT username, language, created, last_login, preferences FROM users');
    }

    public function hasUserData()
    {
        return (bool)$this->user;
    }

    public function userData()
    {
        return $this->user->fetchArray(SQLITE3_ASSOC);
    }

    public function contacts()
    {
        $result = $this->query('SELECT contact_id, email, name, changed, firstname, surname, vcard, words FROM contacts');
        $contacts = [];
        if ($result)
        {
            while($contact = $result->fetchArray(SQLITE3_ASSOC))
            {
                array_push($contacts, $contact);
            }
        }
        return $contacts;
    }

    public function identities()
    {
        $identities = [];
        $result = $this->query('SELECT email, standard, name, changed, organization, "reply-to", bcc, signature, html_signature from identities');
        if ($result) {
            while ($identity = $result->fetchArray(SQLITE3_ASSOC))
            {
                array_push($identities, $identity);
            }
        }
        return $identities;
    }

    public function getContactGroups($contact_id)
    {
        $groups = [];
        $result = $this->query("
            SELECT cg.name, cg.changed, cm.created
            FROM contactgroupmembers cm
            LEFT JOIN contactgroups cg ON cm.contactgroup_id = cg.contactgroup_id
            WHERE cm.contact_id = $contact_id
        ");
        if ($result) {
            while ($row = $result->fetchArray(SQLITE3_ASSOC))
            {
                array_push($groups, $row);
            }
        }
        
        return $groups;
    }
}

class RoundcubeDBList
{
    public function __construct()
    {
        $this->list = [];
        $this->xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><ROUNDCUBE/>");
    }

    public function push($db)
    {
        $this->list[] = $db;
    }

    private function assocToXml($el, $assocArray)
    {
        if ($assocArray) {
            foreach($assocArray as $key => $value) {
                $el->addChild(strtoupper($key), urlencode($value));
            }
        }
    }

    private function buildXML()
    {
        foreach($this->list as $db) {
            $this->insertEmail($db);
        }
        return $this->xml;
    }

    public function hasEmails()
    {
        return (count($this->list) > 0);
    }

    private function insertEmail($db)
    {
        $el = $this->xml->addChild('EMAIL');
        $this->assocToXML($el, $db->userData());
        $identities_el = $el->addChild('INDENTITIES');
        foreach($db->identities() as $identity)
        {
            $this->assocToXML(
                $identities_el->addChild('INDENTITY'),
                $identity
            );
        }
        $contacts_el = $el->addChild('CONTACTS');
        foreach($db->contacts() as $contact)
        {
            $contact_id = $contact['contact_id'];
            unset($contact['contact_id']);
            $contact_el = $contacts_el->addChild('CONTACT');
            $this->assocToXML($contact_el, $contact);
            $groups_el = $contact_el->addChild('GROUPS');
            foreach ($db->getContactGroups($contact_id) as $group) {
                $this->assocToXML($groups_el->addChild('GROUP'), $group);
            }
        }
    }

    public function getXML()
    {
        $domxml = dom_import_simplexml($this->buildXML());
        return $domxml->ownerDocument->saveXML($domxml->ownerDocument->documentElement);
    }

    public function saveXML($filename)
    {
        $xmlpath = dirname($filename);
        if(!file_exists($xmlpath))
        {
            if (!mkdir($xmlpath, 0777, true))
            {
                die("Unable to create XML path: $xmlpath. Unable to backup RoundCube data");
            };
        }
        if (!file_put_contents($filename, $this->getXML()))
        {
            die("Unable to open $filename for writing. Unable to backup RoundCube data");
        }
        echo "Backup saved.\n";
    }
}

function processArguments($argv) {
    $arguments = [
        'output' => false,
        'pattern' => false,
        'files' => [],
    ];
    foreach(array_slice($argv, 1) as $argument)
    {
        if (preg_match('/^--(?P<arg>\w+)=(?P<value>.*)$/',$argument, $matches))
        {
            $arguments[$matches['arg']] = $matches['value'];
        } else {
            array_push($arguments['files'], $argument);
        }
    }
    if ($arguments['pattern']) { // pattern given, merging results to files list
        $matchedFiles = glob($arguments['pattern']);
        if ($matchedFiles === FALSE) {
            // Glob experienced error ()
            echo "WARN: glob matching failed";
            $matchedFiles = []; // mimic no matched files behavior and continue
        }
        $arguments['files'] = array_unique(
            array_merge(
                $arguments['files'],
                $matchedFiles
            )
        );
        unset($arguments['pattern']);
    }
    return $arguments;
}

// Start Process
$arguments = processArguments($argv);

if (count($arguments['files']) === 0 || !$arguments['output'])
{
    echo "Usage: php cpanel_sqlite_da_roundcube.php --output=/root/output.xml --pattern=/root/backup/homedir/etc/domain.com/*.rcube.db\n";
    echo "Usage: php cpanel_sqlite_da_roundcube.php --output=/root/output.xml info.rcube.db support.rcube.db\n";
    exit();
}

echo "Generating ".$arguments['output']."...\n";

$files = [];
foreach ($arguments['files'] as $file)
{
    if (file_exists($file)) {
        array_push($files, $file);
    }
    else {
        echo "WARN: File $file does not exists, skipping it...\n";
    }
}

if (count($files) > 0)
{
    $db_list = new RoundcubeDBList();
    foreach ($files as $file)
    {
        $db = new RoundcubeDB($file);
        if ($db->hasUserData()) {
            $db_list->push($db);
        }
    }

    if ($db_list->hasEmails())
    {
        $db_list->saveXML($arguments['output']);
    }
    else {
        echo "WARN: no emails in given files, no xml file produced";
        exit();
    }
}
else
{
    echo "WARN: no files given, no xml file produced";
    exit();
}
?>
