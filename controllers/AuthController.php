<?php

use Pimcore\Model\Object;

use Members\Controller\Action;
use Members\Model\Configuration;
use Members\Tool\Identifier;

class Members_AuthController extends Action
{
    /**
     *
     */
    public function loginFromAreaAction()
    {
        if ($this->hasParam('lang') && !empty($this->getParam('lang'))) {
            $locale = new \Zend_Locale($this->getParam('lang'));
            \Zend_Registry::set('Zend_Locale', $locale);
        }

        $loginData = $this->parseLoginAction();

        if ($loginData['error'] === TRUE) {
            $this->_helper->flashMessenger([
                'mode' => 'area',
                'type' => 'danger',
                'text' => $this->translate->_($loginData['message']),
            ]);

            if (!empty($loginData['redirect'])) {
                $this->redirect($loginData['redirect']);
            }
        } elseif ($loginData['error'] === FALSE) {
            if (!empty($loginData['redirect'])) {
                $this->redirect($loginData['redirect']);
            }
        }
    }

    /**
     *
     */
    public function loginAction()
    {
        $requestBackUri = NULL;
        if ($this->hasParam('back') && !empty($this->getParam('back'))) {
            $requestBackUri = $this->getParam('back');
            //allow to modify redirect url
            $results = \Pimcore::getEventManager()->trigger('members.login.back.redirect', NULL, [
                'redirect' => $requestBackUri,
                'origin'   => $this->getRequest()->getRequestUri()
            ]);
            if ($results->count()) {
                $requestBackUri = $results->last();
            }
        }

        $this->view->back = !is_null($requestBackUri)
            ? $requestBackUri
            : (\Members\Model\Configuration::getLocalizedPath('routes.login.redirectAfterSuccess')
                ? \Members\Model\Configuration::getLocalizedPath('routes.login.redirectAfterSuccess')
                : \Members\Model\Configuration::getLocalizedPath('routes.profile')
            );

        $loginData = $this->parseLoginAction();

        if ($loginData['error'] === TRUE && $loginData['message'] === 'ALREADY_LOGGED_IN') {
            $this->redirect($loginData['redirect']);
        } elseif ($loginData['error'] === TRUE) {
            $this->view->error = $this->translate->_($loginData['message']);
        } elseif ($loginData['error'] === FALSE) {
            if (!empty($loginData['redirect'])) {
                $this->redirect($loginData['redirect']);
            }
        }
    }

    /**
     *
     */
    public function loginAjaxAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            die('invalid ajax request.');
        }

        $loginData = $this->parseLoginAction();

        $userData = FALSE;
        $htmlTemplate = NULL;

        if ($loginData['error'] === FALSE) {
            $userData = [
                'firstName' => $this->auth->getIdentity()->getFirstname(),
                'lastname'  => $this->auth->getIdentity()->getFirstname(),
                'email'     => $this->auth->getIdentity()->getEmail()
            ];

            $htmlTemplate = $this->view->partial(
                'members/auth/ajax/success.php',
                [
                    'user'             => $userData,
                    'message'          => $loginData['message'],
                    'logoutUrl'        => Configuration::getLocalizedPath('routes.logout'),
                    'additionalParams' => $this->getParam('additionalParams')
                ]
            );
        } else {
            $htmlTemplate = $this->view->partial(
                'members/auth/ajax/error.php',
                [
                    'message' => $loginData['message']
                ]
            );
        }

        $this->_helper->json([
            'success'     => !$loginData['error'],
            'message'     => $loginData['message'] === 'ALREADY_LOGGED_IN' ? $loginData['message'] : $this->translate->_($loginData['message']),
            'redirectUrl' => $loginData['redirect'],
            'user'        => $userData,
            'html'        => $htmlTemplate
        ]);
    }

    /**
     * @return array
     */
    private function parseLoginAction()
    {
        $error = FALSE;
        $message = NULL;
        $redirect = NULL;

        if ($this->_helper->member()) {
            $error = TRUE;
            $message = 'ALREADY_LOGGED_IN';
            $redirect = Configuration::getLocalizedPath('routes.profile');
        } else {
            if ($this->_request->isPost()) {
                $identity = trim($this->_getParam('email'));
                $password = $this->_getParam('password');

                if (empty($identity) || empty($password)) {
                    $error = TRUE;
                    $message = 'Wrong email or password';
                } else {
                    $identifier = new Identifier();
                    if ($identifier->setIdentity($identity, $password)->isValid()) {
                        /**
                         * Set the Session Cookie to 7 Days.
                         */
                        if (!is_null($this->_getParam('remember'))) {
                            \Zend_Session::rememberMe(604800);
                        }

                        if ($this->_getParam('back')) {
                            $backParam = $this->_getParam('back');
                            if (is_numeric($backParam)) {
                                $redirect = \Pimcore\Model\Document::getById($backParam)->getFullPath();
                            } else {
                                $redirect = $backParam;
                            }

                            //allow to modify redirect url
                            $results = \Pimcore::getEventManager()->trigger('members.login.back.redirect', NULL, [
                                'redirect' => $redirect,
                                'origin'   => $this->getParam('origin')
                            ]);

                            if ($results->count()) {
                                $redirect = $results->last();
                            }

                        } else {
                            $redirect = Configuration::getLocalizedPath('routes.profile');
                        }

                        $message = 'You\'ve been successfully logged in';
                    } else {
                        switch ($identifier->getCode()) {
                            case \Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID:
                            case \Zend_Auth_Result::FAILURE_IDENTITY_NOT_FOUND:
                                $error = TRUE;
                                $message = 'Wrong email or password';
                                break;
                            default:
                                $error = TRUE;
                                $message = 'Unexpected error occurred';
                                break;
                        }
                    }
                }
            }
        }

        if ($error && $this->hasParam('origin')) {
            $redirect = $this->getParam('origin');
        }

        return ['error' => $error, 'message' => $message, 'redirect' => $redirect];
    }

    /**
     *
     */
    public function logoutAction()
    {
        $this->auth->clearIdentity();
        \Pimcore::getEventManager()->trigger('members.action.logout');

        $this->redirect(Configuration::getLocalizedPath('routes.login'));
    }

    /**
     *
     */
    public function passwordRequestAction()
    {
        if ($this->_helper->member()) {
            $this->redirect(Configuration::getLocalizedPath('routes.profile'));
        }

        if ($this->_request->isPost()) {
            $email = trim($this->_request->getPost('email'));
            if (!\Zend_Validate::is($email, 'EmailAddress')) {
                $this->view->error = $this->translate->_('Provide valid email address.');

                return;
            }

            // TODO resend confirmation email if account is not active
            $list = Object\Member::getByEmail($email);

            if (count($list) == 0) {
                $this->view->error = $this->translate->_('User with given email not exist.');

                return;
            }

            /** @var \Pimcore\Model\Object\Member $member */
            $member = $list->current();
            $member->requestPasswordReset();
            $this->_helper->flashMessenger([
                'type' => 'success',
                'text' => $this->translate->_('Password reset confirmation was sent to given email address.'),
            ]);

            $this->redirect(Configuration::getLocalizedPath('routes.login'));
        }
    }

    /**
     *
     */
    public function passwordResetAction()
    {
        if ($this->_helper->member()) {
            $this->redirect(Configuration::getLocalizedPath('routes.profile'));
        }

        $hash = trim($this->_getParam('hash'));

        if (empty($hash)) {
            $this->_helper->flashMessenger([
                'type' => 'danger',
                'text' => $this->translate->_('Invalid password reset link.'),
            ]);

            $this->redirect(Configuration::getLocalizedPath('routes.login'));
        }

        $list = new Object\Member\Listing();
        $list->setUnpublished(TRUE);
        $list->setCondition('resetHash = ?', $hash);

        if (count($list) == 0) {
            $this->_helper->flashMessenger([
                'type' => 'danger',
                'text' => $this->translate->_('Invalid password reset link.')
            ]);

            $this->redirect(Configuration::getLocalizedPath('routes.login'));
        }

        if ($this->_request->isPost()) {
            $post = $this->_request->getPost();
            /** @var \Pimcore\Model\Object\Member $member */
            $member = $list->current();
            $result = $member->resetPassword($post);
            if ($result->isValid()) {
                $this->_helper->flashMessenger([
                    'type' => 'success',
                    'text' => $this->translate->_('Your password has been successfully changed.')
                ]);

                $this->redirect(Configuration::getLocalizedPath('routes.login'));
            }

            $this->view->errors = $result->getMessages();
        }
    }

}