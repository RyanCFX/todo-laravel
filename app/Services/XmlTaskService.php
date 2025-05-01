<?php

namespace App\Services;

use App\Models\Task;
use SimpleXMLElement;
use Illuminate\Support\Collection;

class XmlTaskService
{
    /**
     * Convierte una colección de tareas a XML
     *
     * @param Collection $tasks
     * @return string
     */
    public function tasksToXml(Collection $tasks): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tasks></tasks>');

        foreach ($tasks as $task) {
            $taskNode = $xml->addChild('task');
            $taskNode->addChild('title', htmlspecialchars($task->title));
            $taskNode->addChild('description', htmlspecialchars($task->description ?? ''));
            $taskNode->addChild('status', $task->status);
            $taskNode->addChild('due_date', $task->due_date ?? '');
            $taskNode->addChild('priority', $task->priority ?? '');
            if (isset($task->created_at->format)) {
                $taskNode->addChild('created_at', $task->created_at->format('Y-m-d H:i:s'));
            }
            if (isset($task->updated_at->format)) {
                $taskNode->addChild('updated_at', $task->updated_at->format('Y-m-d H:i:s'));
            }
        }

        return $xml->asXML();
    }

    /**
     * Convierte XML a un array de tareas
     *
     * @param string $xml
     * @return array
     */
    public function xmlToTasks(string $xml): array
    {
        $xmlObj = new SimpleXMLElement($xml);
        $tasks = [];

        foreach ($xmlObj->task as $taskNode) {
            $tasks[] = [
                'title' => (string)$taskNode->title,
                'description' => (string)$taskNode->description,
                'status' => (string)$taskNode->status,
                'due_date' => (string)$taskNode->due_date ?: null,
                'priority' => (string)$taskNode->priority ?: null,
            ];
        }

        return $tasks;
    }

    /**
     * Valida que el XML tenga el formato correcto
     *
     * @param string $xml
     * @return bool
     */
    public function validateXml(string $xml): bool
    {
        try {
            $dom = new \DOMDocument();
            $dom->loadXML($xml);
            
            $schema = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:element name="tasks">
        <xs:complexType>
            <xs:sequence>
                <xs:element name="task" maxOccurs="unbounded" minOccurs="0">
                    <xs:complexType>
                        <xs:sequence>
                            <xs:element name="title" type="xs:string"/>
                            <xs:element name="description" type="xs:string"/>
                            <xs:element name="status" type="xs:string"/>
                            <xs:element name="due_date" type="xs:string"/>
                            <xs:element name="priority" type="xs:string"/>
                        </xs:sequence>
                    </xs:complexType>
                </xs:element>
            </xs:sequence>
        </xs:complexType>
    </xs:element>
</xs:schema>
XML;

            $schemaDoc = new \DOMDocument();
            $schemaDoc->loadXML($schema);
            
            return $dom->schemaValidateSource($schemaDoc->saveXML());
        } catch (\Exception $e) {
            return false;
        }
    }
} 