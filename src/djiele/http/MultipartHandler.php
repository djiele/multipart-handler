<?php

namespace djiele\http;

use djiele\exceptions\IOException;

class MultipartHandler
{
    protected $boundary;
    protected $files;
    protected $vars;

    /**
     * MultipartHandler constructor.
     */
    public function __construct()
    {

        $this->boundary = '--' . (explode('=', self::getHttpContentTypeHeader())[1]) . "\r\n";
        $this->files = [];
        $this->vars = [];

        $this->parseRequestBody();
    }

    /**
     * Get the boundary id of the request body
     *
     * @return string
     */
    public function getBoundary()
    {
        return $this->boundary;
    }

    /**
     * Get the input files infos
     *
     * @return array
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * Get the input variables
     *
     * @return array
     */
    public function getVars()
    {
        return $this->vars;
    }

    /**
     * populate both $_POST and $_FILES super global variables
     */
    public function populateGlobals()
    {
        $this->populatePostGlobals();
        $this->populateFilesGlobals();
    }

    /**
     * Populate the $_POST super global variable
     */
    public function populatePostGlobals()
    {
        $matches = [];
        foreach ($this->vars as $v) {
            if (preg_match('/^(.*)\[(.*)\]$/', key($v), $matches)) {
                $varname = self::sanitizeVarname($matches[1]);
                if (is_array($_POST[$varname])) {
                    if (empty($matches[2])) {
                        $_POST[$varname][] = current($v);
                    } else {
                        $_POST[$varname][$matches[2]] = current($v);
                    }
                } else {
                    $_POST[$varname] = [current($v)];
                }
            } else {
                $varname = self::sanitizeVarname(key($v));
                $_POST[$varname] = current($v);
            }
        }
    }

    /**
     * Populate the $_FILES super global variable
     */
    public function populateFilesGlobals()
    {
        $matches = [];
        foreach ($this->files as $f) {
            $varname = $f['@_from_input_name'];
            unset($f['@_from_input_name']);
            if (preg_match('/^(.*)\[(.*)\]$/', $varname, $matches)) {
                $varname = self::sanitizeVarname($matches[1]);
                if (is_array($_FILES[$varname])) {
                    if ('' == $matches[2]) {
                        foreach ($f as $k => $v) {
                            $_FILES[$varname][$k][] = $v;
                        }
                    } else {
                        $_FILES[$varname][$matches[2]] = $f;
                    }
                } else {
                    if ('' == $matches[2]) {
                        foreach ($f as $k => $v) {
                            $_FILES[$varname][$k][] = $v;
                        }
                    } else {
                        $_FILES[$varname][$matches[2]] = $f;
                    }
                }
            } else {
                $varname = self::sanitizeVarname($varname);
                $_FILES[$varname] = $f;
            }
        }
    }

    /**
     * Extract variables and files from the multipart request body and set $vars and $files internal variables
     */
    protected function parseRequestBody()
    {
        $temporaryFiles = [];
        $boundaryLen = strlen($this->boundary);
        $boundaryEnds = rtrim($this->boundary) . '--' . "\r\n";
        $varIndex = -1;
        $fileIndex = -1;

        if (is_resource($inputStream = fopen("php://input", "rb"))) {
            $inHeader = true;
            while (!feof($inputStream)) {
                if ($inHeader) {
                    $headerLine = fgets($inputStream);
                    if ($this->boundary == $headerLine) {
                        continue;
                    } elseif ('' == trim($headerLine)) {
                        $inHeader = false;
                        continue;
                    }
                    $headerValue = ltrim(substr($headerLine, strpos($headerLine, ':') + 1));
                    if (0 === strpos($headerValue, 'form-data;')) {
                        $parsedVars = self::parseFormDataHeader($headerLine);
                    } else {
                        $headerName = substr($headerLine, 0, strpos($headerLine, ':'));
                        $parsedVars[$headerName] = substr($headerValue, 0, -2);
                    }
                } else {
                    if (array_key_exists('filename', $parsedVars)) {
                        ++$fileIndex;
                        $isFileupload = true;
                        $temporaryFiles[] = tmpfile();
                        $this->files[$fileIndex] = [
                            '@_from_input_name' => $parsedVars['name'],
                            'name' => $parsedVars['filename'],
                            'type' => (isset($parsedVars['Content-Type']) ? $parsedVars['Content-Type'] : 'application/octet-stream'),
                            'size' => 0,
                            'tmp_name' => stream_get_meta_data($temporaryFiles[count($temporaryFiles) - 1])['uri'],
                            'error' => UPLOAD_ERR_NO_FILE,
                        ];
                    } else {
                        ++$varIndex;
                        $isFileupload = false;
                        $this->vars[$varIndex] = [];
                    }
                    do {
                        if (false === ($bytes = fgets($inputStream, 8192))) {
                            if ($isFileupload) {
                                ftruncate(
                                    $temporaryFiles[count($temporaryFiles) - 1],
                                    ftell($temporaryFiles[count($temporaryFiles) - 1]) - $boundaryLen - 2
                                );
                                $this->files[$fileIndex]['size'] = filesize($this->vars[$parsedVars['name']]['tmp_name']);
                                $this->files[$fileIndex]['error'] = UPLOAD_ERR_OK;
                            } else {
                                $this->vars[$varIndex][$parsedVars['name']] = substr($this->vars[$varIndex], 0, -$boundaryLen - 4);
                            }
                            break;
                        } else {
                            if ($bytes == $this->boundary || $bytes == $boundaryEnds) {
                                if ($isFileupload) {
                                    ftruncate(
                                        $temporaryFiles[count($temporaryFiles) - 1],
                                        ftell($temporaryFiles[count($temporaryFiles) - 1]) - 2
                                    );
                                    $this->files[$fileIndex]['size'] = filesize($this->files[$fileIndex]['tmp_name']);
                                    $this->files[$fileIndex]['error'] = UPLOAD_ERR_OK;
                                } else {
                                    $this->vars[$varIndex][$parsedVars['name']] = substr($this->vars[$varIndex][$parsedVars['name']], 0, -2);
                                }
                                $inHeader = true;
                                break;
                            } else {
                                if ($isFileupload) {
                                    fwrite($temporaryFiles[count($temporaryFiles) - 1], $bytes, strlen($bytes));
                                } else {
                                    $this->vars[$varIndex][$parsedVars['name']] .= $bytes;
                                }
                            }
                        }
                    } while (!feof($inputStream));
                }
            }
            fclose($inputStream);
        } else {
           throw new IOException('Could not open file for reading');
        }
    }

    /**
     * Filter invalid chars for a PHP variable name
     *
     * @param $varname
     * @return string|string[]|null
     */
    public static function sanitizeVarname($varname)
    {
        $varname = preg_replace('/[^a-zA-Z0-9_\x7f-\xff]/', '_', $varname);
        return preg_replace('/_+/', '_', $varname);
    }

    /**
     * Extract variable name and filename from given Content-Disposition header
     *
     * @param $header
     * @return array
     */
    public static function parseFormDataHeader($header)
    {
        $ret = [];
        $headerValue = ltrim(substr($header, strpos($header, ':') + 1));
        $headerValue = ltrim(substr($headerValue, strlen('form-data;')));
        while (0 < strlen($headerValue)) {
            $attr = substr($headerValue, 0, strpos($headerValue, '='));
            $headerValue = substr($headerValue, strpos($headerValue, '=') + 1);
            if ('' == $headerValue) {
                break;
            }
            if ('"' == $headerValue[0]) {
                $searchStartAt = 1;
                do {
                    $pos = strpos($headerValue, '"', $searchStartAt);
                    if ('\\' == $headerValue[$pos - 1]) {
                        $searchStartAt = $pos + 1;
                        $search = true;
                    } else {
                        $search = false;
                    }
                } while ($search);

                $value = str_replace('\\', '', substr($headerValue, 1, $pos - 1));
                $ret[$attr] = $value;
                $headerValue = ltrim(substr($headerValue, $pos + 1), "; ");
                if ('"' == $headerValue) {
                    $headerValue = '';
                    break;
                }
            } else {
                $value = substr($headerValue, 0, strpos($headerValue, ';'));
            }
        }
        return $ret;
    }

    /**
     * Extract Content-Type header from current request headers
     * @return string|null
     */
    public static function getHttpContentTypeHeader()
    {
        foreach (getallheaders() as $kh => $vh) {
            if ('Content-Type' == $kh) {
                return $vh;
            }
        }
        return null;
    }
}