<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Edit a document interactively, by launching $EDITOR (like vi or nano).
 *
 *   $result = id(new InteractiveEditor($document))
 *     ->setName('shopping_list')
 *     ->setLineOffset(15)
 *     ->editInteractively();
 *
 * This will launch the user's $EDITOR to edit the specified '$document', and
 * return their changes into '$result'.
 *
 * @task create  Creating a New Editor
 * @task edit    Editing Interactively
 * @task config  Configuring Options
 * @group console
 */
final class PhutilInteractiveEditor {

  private $name     = '';
  private $content  = '';
  private $offset   = 0;
  private $fallback = 'nano';


/* -(  Creating a New Editor  )---------------------------------------------- */


  /**
   * Constructs an interactive editor, using the text of a document.
   *
   * @param  string  Document text.
   * @return $this
   *
   * @task   create
   */
  public function __construct($content) {
    $this->setContent($content);
  }


/* -(  Editing Interactively )----------------------------------------------- */


  /**
   * Launch an editor and edit the content. The edited content will be
   * returned.
   *
   * @return string    Edited content.
   * @throws Exception The editor exited abnormally or something untoward
   *                   occurred.
   *
   * @task edit
   */
  public function editInteractively() {
    $name = $this->getName();
    $content = $this->getContent();

    $tmp = Filesystem::createTemporaryDirectory('edit.');
    $path = $tmp.DIRECTORY_SEPARATOR.$name;

    try {
      Filesystem::writeFile($path, $content);
    } catch (Exception $ex) {
      Filesystem::remove($tmp);
      throw $ex;
    }

    $editor = $this->getEditor();
    $offset = $this->getLineOffset();

    $err = $this->invokeEditor($editor, $path, $offset);

    if ($err) {
      Filesystem::remove($tmp);
      throw new Exception("Editor exited with an error code (#{$err}).");
    }

    try {
      $result = Filesystem::readFile($path);
      Filesystem::remove($tmp);
    } catch (Exception $ex) {
      Filesystem::remove($tmp);
      throw $ex;
    }

    $this->setContent($result);

    return $this->getContent();
  }

  private function invokeEditor($editor, $path, $offset) {
    $arg_offset = escapeshellarg($offset);
    $arg_path   = escapeshellarg($path);

    $invocation_command = "{$editor} +{$arg_offset} {$arg_path}";

    // Special cases for known editors that don't obey the usual convention.
    if (preg_match('/^mate/', $editor)) {
      $invocation_command = "{$editor} -l {$arg_offset} {$arg_path}";
    }

    // Ensure the child process shares the real STD[IN|OUT] and not a
    // pipe.  This is necessary for emacsclient to work properly.
    $pipes = array();
    return proc_close(proc_open($invocation_command,
                                array(STDIN, STDOUT, STDERR), $pipes));

  }


/* -(  Configuring Options )------------------------------------------------- */


  /**
   * Set the line offset where the cursor should be positioned when the editor
   * opens. By default, the cursor will be positioned at the start of the
   * content.
   *
   * @param  int   Line number where the cursor should be positioned.
   * @return $this
   *
   * @task config
   */
  public function setLineOffset($offset) {
    $this->offset = (int)$offset;
    return $this;
  }


  /**
   * Get the current line offset. See setLineOffset().
   *
   * @return int   Current line offset.
   *
   * @task config
   */
  public function getLineOffset() {
    return $this->offset;
  }


  /**
   * Set the document name. Depending on the editor, this may be exposed to
   * the user and can give them a sense of what they're editing.
   *
   * @param  string  Document name.
   * @return $this
   *
   * @task config
   */
  public function setName($name) {
    $name = preg_replace('/[^A-Z0-9._-]+/i', '', $name);
    $this->name = $name;
    return $this;
  }


  /**
   * Get the current document name. See setName() for details.
   *
   * @return string  Current document name.
   *
   * @task config
   */
  public function getName() {
    if (!strlen($this->name)) {
      return 'untitled';
    }
    return $this->name;
  }


  /**
   * Set the text content to be edited.
   *
   * @param  string  New content.
   * @return $this
   *
   * @task config
   */
  public function setContent($content) {
    $this->content = $content;
    return $this;
  }


  /**
   * Retrieve the current content.
   *
   * @return string
   *
   * @task config
   */
  public function getContent() {
    return $this->content;
  }


  /**
   * Set the fallback editor program to be used if the env variable $EDITOR
   * is not available and there is no `editor` binary in PATH.
   *
   * @param  string  Command-line editing program (e.g. 'emacs', 'vi')
   * @return $this
   *
   * @task config
   */
  public function setFallbackEditor($editor) {
    $this->fallback = $editor;
    return $this;
  }


  /**
   * Get the name of the editor program to use. The value of the environmental
   * variable $EDITOR will be used if available; otherwise, the `editor` binary
   * if present; otherwise the best editor will be selected.
   *
   * @return string  Command-line editing program.
   *
   * @task config
   */
  public function getEditor() {
    $editor = getenv('EDITOR');
    if ($editor) {
      return $editor;
    }

    // Look for `editor` in PATH, some systems provide an editor which is
    // linked to something sensible.
    list($err) = exec_manual('which editor');
    if (!$err) {
      return 'editor';
    }

    return $this->fallback;
  }
}
