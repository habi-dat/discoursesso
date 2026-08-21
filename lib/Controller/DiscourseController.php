<?php
namespace OCA\DiscourseSSO\Controller;

use OCP\IRequest;
use OCP\IConfig;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Controller;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\IURLGenerator;
use OCP\Authentication\IApacheBackend;
use OCP\User\Backend\ICustomLogout;
use OCP\Util;
use Cviebrock\DiscoursePHP\SSOHelper;

class DiscourseController extends Controller {
    private $userId;
    private $config;
    private $logger;
    private $userManager;
    private $userSession;
    private $groupManager;
    private $urlGenerator;

    public function __construct($AppName, IRequest $request, IConfig $config, IUserManager $userManager,IGroupManager $groupManager, IUserSession $userSession, IURLGenerator $urlGenerator, $UserId){
            parent::__construct($AppName, $request);
            $this->userId = $UserId;
            $this->config = $config;
            $this->userManager = $userManager;
            $this->userSession = $userSession;
            $this->groupManager = $groupManager;
            $this->urlGenerator = $urlGenerator;
    }


	private function replaceWhitespaces($string) {
		$replaceString = $this->config->getAppValue($this->appName, 'replace_whitespaces', '');
		if ($replaceString !== '') {
			return preg_replace('/\s+/', $replaceString, $string);
		} else {
			return $string;
		}
	}

	private function removeTitleInDisplayName($name) {
		$scanString = $this->config->getAppValue($this->appName, 'scan_for_title', '');
		if ($scanString !== '') {
			preg_match_all($scanString, $name, $aMatches);
			$title = end($aMatches[0]);
			if (!$title) {
			    return $name;
			} else {
			    return trim(str_replace($title, "", $name));
			}
		} else {
			return $name;
		}		
	}

	private function getAvatarUrl($name) {
	    // strip trailing slash if found
	    $url = rtrim($this->config->getAppValue($this->appName, 'avatar_url', ''), '/');
	    $header = $this->config->getAppValue($this->appName, 'avatar_token', '');

	    if ($url !== '') {
            // add trailing slash
            $url .= '/';

            if ($header !== '') {
               	return $url.$name.$header;
            } else {
                return $url.$name;
            }
	    }
	}

	private function getTitle($name) {
		$scanString = $this->config->getAppValue($this->appName, 'scan_for_title', '');
		if ($scanString !== '') {
			preg_match_all($scanString, $name, $aMatches);
			$title = end($aMatches[1]);
			if (!$title) {
			    return '';
			} else {
			    return $title;
			}
		} else {
			return '';
		}
	}


	/**
	 * CAUTION: the @Stuff turns off security checks; for this page no admin is
	 *          required and no CSRF check. If you don't know what CSRF is, read
	 *          it up in the docs or you might create a security hole. This is
	 *          basically the only required method to add this exemption, don't
	 *          add it to any other method if you don't exactly know what it does
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function sso($sso, $sig) {
		$ssoHelper = new SSOHelper();

		// this should be the same in your code and in your Discourse settings:
		$secret = $this->config->getAppValue($this->appName, 'clientsecret', '');
		$ssoHelper->setSecret( $secret );

		// load the payload passed in by Discourse
		$payload = $sso;
		$signature = $sig;

		// validate the payload
		if (!($ssoHelper->validatePayload($payload,$signature))) {
		    // invaild, deny
		    header("HTTP/1.1 403 Forbidden");
		    echo("Bad SSO request");
		    die();
		}

		$nonce = $ssoHelper->getNonce($payload);

		$user = $this->userManager->get($this->userId);
 

        $userId = $this->userId;
        $userEmail = $user->getEMailAddress();
        $displayName = $user->getDisplayName();

        $extraParameters = array(
             'username' => $this->replaceWhitespaces($userId),
             'name'     => $this->removeTitleInDisplayName($displayName),
             'title'    => $this->getTitle($displayName),
             'avatar_url' => $this->getAvatarUrl($this->replaceWhitespaces($userId)),
             'avatar_force_update' => $this->config->getAppValue($this->appName, 'force_update', '')
        );

		$excludeGroups = $this->config->getAppValue($this->appName, 'exclude_groups', '');

		if ($excludeGroups !== "true") {
	 		$add_groups = '';
	        $remove_groups = '';
	        $allGroups = $this->groupManager->search('', null, null);
	        foreach($allGroups as $group) {
	                if (!($this->groupManager->isInGroup($this->userId, $group->getGID()))) {
	                  $remove_groups = $remove_groups.$group->getGID().',';
	                } else {
	                  $add_groups = $add_groups.$group->getGID().',';
	                }
	            }
        	$extraParameters['add_groups'] = $this->replaceWhitespaces($add_groups);
        	$extraParameters['remove_groups'] = $this->replaceWhitespaces($remove_groups);
        	$extraParameters['groups'] = $this->replaceWhitespaces($add_groups);
        }

		// build query string and redirect back to the Discourse site
		$query = $ssoHelper->getSignInString($nonce, $this->replaceWhitespaces($userId), $userEmail, $extraParameters);
		$url = $this->config->getAppValue($this->appName, 'clienturl', '');

		return new RedirectResponse($url . '/session/sso_login?' . $query);
	}

	/**
	 * CAUTION: the @Stuff turns off security checks; for this page no admin is
	 *          required and no CSRF check. If you don't know what CSRF is, read
	 *          it up in the docs or you might create a security hole. This is
	 *          basically the only required method to add this exemption, don't
	 *          add it to any other method if you don't exactly know what it does
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function logout() {
		$url = $this->getLogoutUrl();
		$returnTo = $this->config->getAppValue($this->appName, 'clienturl', '');
		$separator = strpos($url, '?') !== false ? '&' : '?';
		return new RedirectResponse($url . $separator . 'returnTo=' . rawurlencode($returnTo));
	}

	/**
	 * Same order as OC_User::getLogoutUrl on Nextcloud 32–34:
	 * active Apache/SAML backend, then ICustomLogout, then core logout.
	 */
	private function getLogoutUrl(): string {
		foreach ($this->userManager->getBackends() as $backend) {
			if ($backend instanceof IApacheBackend && $backend->isSessionActive()) {
				$url = $backend->getLogoutUrl();
				if ($url !== '') {
					return $url;
				}
			}
		}

		$user = $this->userSession->getUser();
		if ($user !== null) {
			$backend = $user->getBackend();
			if ($backend instanceof ICustomLogout) {
				$url = $backend->getLogoutUrl();
				if ($url !== '') {
					return $url;
				}
			}
		}

		return $this->urlGenerator->linkToRoute('core.login.logout')
			. '?requesttoken=' . urlencode(Util::callRegister());
	}

}
