<?php

/**
 * Class Handler
 */
class Handler
{
    /**
     * @param $dirPath
     *
     * @return array
     *
     * @throws Exception
     */
    public function handle($dirPath)
    {
        $dir = opendir($dirPath);

        if ($dir === false) {
            throw new Exception('Failed open dir');
        }

        $bookList = [];
        while (false !== ($file = readdir($dir))) {

            if (!$this->checkFile($file)) {
                continue;
            }

            try {
                if ($this->getFileType($file) === 'fb2') {
                    $bookList[] = $this->parseFb2($dirPath . '/' . $file);
                } else {
                    $bookList[] = $this->parseEpub($dirPath . '/' . $file);
                }
            } catch (Exception $ex) {
                $bookList['error'] = $ex->getMessage();
                continue;
            }
        }
        closedir($dir);

        return $bookList;
    }

    /**
     * @param $path
     *
     * @return string
     *
     * @throws Exception
     */
    private function parseFb2($path)
    {
        $doc = new DOMDocument();
        $doc->strictErrorChecking = false;
        $doc->recover = true;
        $load = $doc->load($path, LIBXML_NOERROR);

        if (!$load) {
            throw new Exception('Load error');
        }

        $description = $doc->getElementsByTagName('description');
        $description = $description->item(0);
        if (!$description) {
            throw new Exception('No description');
        }

        $titleInfo = $description->getElementsByTagName('title-info')->item(0);

        // Название книги
        $bookName = $titleInfo->getElementsByTagName('book-title')->item(0)->nodeValue;

        $authorsList = $titleInfo->getElementsByTagName('author');

        // Имена авторов
        $authors = [];
        foreach ((array)$authorsList as $author) {
            $name = $author->getElementsByTagName('first-name')->item(0)->nodeValue;
            $name .= ' ' . $author->getElementsByTagName('middle-name')->item(0)->nodeValue;
            $name .= ' ' . $author->getElementsByTagName('last-name')->item(0)->nodeValue;

            $authors[] = $name;
        }

        $publishInfo = $description->getElementsByTagName('publish-info')->item(0);
        // Издатель
        $publisher = $publishInfo->getElementsByTagName('publisher')->item(0)->nodeValue;
        // Год издания
        $year = $publishInfo->getElementsByTagName('year')->item(0)->nodeValue;

        return $bookName . ', ' . implode(', ', $authors) . ', ' . $publisher . ', ' . $year . "\n";
    }

    /**
     * @param $path
     *
     * @return mixed
     *
     * @throws Exception
     */
    private function parseEpub($path)
    {
        $zip = new ZipArchive();
        if(!$zip->open($path)){
            throw new Exception('Failed to read epub file');
        }
        $data = $zip->getFromName('OEBPS/content.opf');
        if ($data == false) {
            throw new Exception('Failed to access epub container data');
        }

        $doc = new DOMDocument();
        $doc->strictErrorChecking = false;
        $doc->recover = true;
        $load = $doc->loadXML($data);

        if (!$load) {
            throw new Exception('Load error');
        }
        $metadata = $doc->getElementsByTagName('metadata');

        return $metadata[0]->nodeValue;
    }

    /**
     * @param $file
     *
     * @return bool
     */
    private function checkFile($file) {
        if (in_array($file, ['.', '..'], true)) {
            return false;
        }

        $type = $this->getFileType($file);

        if (!in_array($type, ['fb2', 'epub'], true)) {
            return false;
        }

        return true;
    }

    /**
     * @param $file
     *
     * @return null
     */
    private function getFileType($file)
    {
        $nameData = explode('.', $file);

        return isset($nameData[count($nameData) - 1]) ? $nameData[count($nameData) - 1] : null;
    }
}

$handler = new Handler();
$result = $handler->handle($argv[1]);

echo implode("\n", $result);
