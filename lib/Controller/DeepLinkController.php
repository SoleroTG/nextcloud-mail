<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Controller;

use OCA\Mail\Db\MailAccountMapper;
use OCA\Mail\Db\MessageMapper;
use OCA\Mail\Service\AccountService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

class DeepLinkController extends Controller {
	private MailAccountMapper $mailAccountMapper;
	private AccountService $accountService;
	private MessageMapper $messageMapper;
	private IURLGenerator $urlGenerator;
	private IUserSession $userSession;

	public function __construct(
		string $appName,
		IRequest $request,
		MailAccountMapper $mailAccountMapper,
		AccountService $accountService,
		MessageMapper $messageMapper,
		IURLGenerator $urlGenerator,
		IUserSession $userSession
	) {
		parent::__construct($appName, $request);
		$this->mailAccountMapper = $mailAccountMapper;
		$this->accountService = $accountService;
		$this->messageMapper = $messageMapper;
		$this->urlGenerator = $urlGenerator;
		$this->userSession = $userSession;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * @param string $messageId
	 * @return RedirectResponse
	 */
	public function open(string $messageId): RedirectResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new RedirectResponse($this->urlGenerator->linkToRouteAbsolute('core.page.login'));
		}

		$userId = $user->getUID();

		try {
			// Ensure formatting: Always wrapped in <>
			$cleanedId = '<' . trim($messageId, '<>') . '>';

			$lightAccounts = $this->mailAccountMapper->findByUserId($userId);
			
			foreach ($lightAccounts as $lightAccount) {
				$accountId = $lightAccount->getId();
				$account = $this->accountService->find($userId, $accountId);
				$messages = $this->messageMapper->findByMessageId($account, $cleanedId);
				
				if (!empty($messages)) {
					$message = $messages[0];
					$targetId = $message->getId();
					
					// IMPORTANT FIX: Use 'mail.page.thread' instead of 'mail.page#thread'
					$url = $this->urlGenerator->linkToRouteAbsolute(
						'mail.page.thread',
						['mailboxId' => $message->getMailboxId(), 'id' => $targetId]
					);
					
					return new RedirectResponse($url);
				}
			}
		} catch (\Exception $e) {
			// Fall through to fallback
		}

		// Fallback
		return new RedirectResponse($this->urlGenerator->linkToRouteAbsolute('mail.page.index', []));
	}
}
