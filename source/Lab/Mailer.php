<?php
/*
 * Copyright (C) 2011-2015 Solver Ltd. All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at:
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on
 * an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the
 * specific language governing permissions and limitations under the License.
 */
namespace Solver\Lab;

/**
 * A quick & simple facade hooking deeply into Swift Mailer, with two purposes:
 *
 * - Providing a simplified, yet capable enough interface for producing typical emails with hybrid text & html content,
 * inline images, attachments.
 *
 * - Working around bad Swift Mailer issues by patching and replacing some functionality in order to produce compliant
 * emails.
 *
 * Because it hacks deeply into Swift Mailer, it's only been tested against Swift Mailer 5.0.3. Compatibility with any
 * other version (including 5.0.x versions) should be manually verified.
 */
class Mailer {
	protected $swiftMailerPath;
	protected $transport = null;
	protected $message = null;
	protected $textBody = null;
	protected $htmlBody = null;
	protected $inlineFiles = [];
	protected $attachedFiles = [];

	/**
	 * @param string $swiftMailerPath
	 * Full path to the Swift Mailer package (for composer this is typically ".../vendors/swiftmailer/swiftmailer/").
	 *
	 * This class will initialize Swift Mailer (so you don't have to do it on every page load) and read some relevant
	 * info.
	 */
	public function __construct($swiftMailerPath) {
		$this->swiftMailerPath = $swiftMailerPath = \rtrim($swiftMailerPath, '\\/');

		$expected = 'Swift-5.0.3';
		$actual = \trim(@\file_get_contents($swiftMailerPath . '/VERSION'));

		if ($actual !== $expected) {
			throw new \Exception('The required version of the package is "' . $expected . '", the provided version is "' . $actual . '".');
		}

		// This will initialize Swift Mailer, but skip its autoloader (add Swift's library directory to Core's autoloader).
		require_once $swiftMailerPath . '/lib/swift_init.php';

		$this->message = new MailerMessage();
	}

	public function useSmtpTransport($user, $password, $host, $port = 25, $security = null) {
		$this->transport = \Swift_SmtpTransport::newInstance($host, $port, $security)
			->setUsername($user)
			->setPassword($password);
	}

	/**
	 * This will use PHP's mail() function (as configured in PHP.ini).
	 */
	public function useBuiltInTransport() {
		$this->transport = \Swift_MailTransport::newInstance('-f%s');
	}

	/**
	 * Resets all message headers, content and attachments, so you can start configuring a new message to send.
	 */
	public function resetMessage() {
		$this->message = new MailerMessage();
		$this->textBody = $this->htmlBody = null;
		$this->inlineFiles = $this->attachedFiles = null;
	}

	public function setFrom($email, $name = null) {
		$this->message->setFrom($this->stripNewlines($email), $name === null ? null : $this->stripNewlines($name));
	}

	public function setSender($email, $name = null) {
		$this->message->setSender($this->stripNewlines($email), $name === null ? null : $this->stripNewlines($name));
	}

	public function setTo($email, $name = null) {
		$this->message->setTo($this->stripNewlines($email), $name === null ? null : $this->stripNewlines($name));
	}

	public function setSubject($subject) {
		$this->message->setSubject($this->stripNewlines($subject));
	}

	public function setTextBody($text) {
		$this->textBody = $text;
	}

	public function setHtmlBody($html) {
		$this->htmlBody = $html;
	}

	/**
	 * @param string $path
	 * Path to a file to include.
	 *
	 * @return string
	 * Returns the string content id ("cid"), which you can use to refer to the file from your, say, html body.
	 */
	public function addInlineFile($path) {
		$file = \Swift_EmbeddedFile::fromPath($path)->setDisposition('inline');
		$cid = 'cid:' . $file->getId();
		$this->inlineFiles[] = $file;
		return $cid;
	}

	/**
	 * @param string $path
	 * Path to a file to include.
	 *
	 * @return string
	 * Returns the string content id ("cid"), which you can use to refer to the file from your, say, html body.
	 */
	public function addAttachedFile($path) {
		$file = \Swift_Attachment::fromPath($path)->setDisposition('attachment');
		$cid = 'cid:' . $file->getId();
		$this->attachedFiles[] = $file;
		return $cid;
	}

	public function send(& $failedRecipientsOut = null) {
		/*
		 * We'll build a compliant message manually, bypassing Swift Mailer, which often doesn't nest and sort the parts
		 * properly. Our reference: http://msdn.microsoft.com/en-us/library/office/aa563064(v=exchg.140).aspx
		 */

		// Body part(s).
		if ($this->textBody !== null && $this->htmlBody !== null) {
			$text = new MailerMimePart($this->textBody, 'text/plain');
			$html = new MailerMimePart($this->htmlBody, 'text/html');
			$root = new MailerMimePart(null, 'multipart/alternative');
			$root->setChildren([$text, $html]);
		} else if ($this->textBody !== null) {
			$root = new MailerMimePart($this->textBody, 'text/plain');
		} else if ($this->htmlBody !== null) {
			$root = new MailerMimePart($this->htmlBody, 'text/html');
		} else {
			throw new \Exception('Your message has neither a text body, nor an html body. Awkward.');
		}

		// Inline files? Wrap in a "related" part.
		if ($this->inlineFiles) {
			$sub = $root;
			$root = new MailerMimePart(null, 'multipart/related');
			$root->setChildren(\array_merge([$sub], $this->inlineFiles));
		}

		// Attached files? Wrap in a "mixed" part.
		if ($this->attachedFiles) {
			$sub = $root;
			$root = new MailerMimePart(null, 'multipart/mixed');
			$root->setChildren(\array_merge([$sub], $this->attachedFiles));
		}

		// "Graft" the resulting structure onto the message.
		$this->message->setContentType($root->getContentType());
		$this->message->setBody($root->getBody());
		$this->message->setChildren($root->getChildren());

		if ($this->transport === null) {
			throw new \Exception('You need to set the mail transport via one of the use*Transport() methods.');
		}

		/*
		 * Ready to send.
		 */
		
		$mailer = new \Swift_Mailer($this->transport);
		return $mailer->send($this->message, $failedRecipientsOut) > 0;
	}

	protected function stripNewlines($string) {
		// Depending on the underlying engine used, leaving new lines in certain strings (subject etc.) might allow
		// header injection, so we filter it just in case (no damage as new lines are not valid for those fields).
		return \str_replace(["\n", "\r"], '', $string);
	}
}

// For internal use only! Use the provided methods of class Mailer to configure your message.
trait MailerMimePartPatch {
	/**
	 * @return Blitz\MailerMessage
	 */
	public function setChildren(array $children, $compoundLevel = null) {
		// This property is used when the part renders.
		MonkeyPatch::set('Swift_Mime_SimpleMimeEntity', $this, '_immediateChildren', $children);

		// This property is used when calling getChildren().
		MonkeyPatch::set('Swift_Mime_SimpleMimeEntity', $this, '_children', $children);

		// If we don't call this, the headers don't include boundaries etc.
		MonkeyPatch::call('Swift_Mime_SimpleMimeEntity', $this, '_fixHeaders');
		return $this;
	}
}

// For internal use only! Use the provided methods of class Mailer to configure your message.
class MailerMimePart extends \Swift_MimePart {
	use MailerMimePartPatch;
}

// For internal use only! Use the provided methods of class Mailer to configure your message.
class MailerMessage extends \Swift_Message {
	use MailerMimePartPatch;
}
