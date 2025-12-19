<?php
/**
 * Created by PhpStorm.
 * User: jeanbaptistecaplan
 * Date: 27/12/2019
 * Time: 00:00
 */

namespace myCLAP\Modules\UserModule\Controlers;


use myCLAP\Controler;
use Plexus\Exception\HttpException;
use Plexus\Session;
use Plexus\Utils\Text;

class UserSession extends Controler {

    /**
     * @throws \Exception
     */
    public function login() {
        // Redirect the the user if he is already connected
        if ($this->getUserModule()->isConnected()) {
            $this->redirect(Session::getLastURL());
            return;
        }

        $envConfig = $this->getContainer()->getConfiguration('environment')->read();
        $cla_auth_host = $envConfig->get('cla-auth-host');
        $cla_auth_identifier = $envConfig->get('cla-auth-identifier');
        $url = Text::format(
            "{}/authentification/{}",
            $cla_auth_host,
            $cla_auth_identifier
        );
        $this->redirect($url);
    }

    /**
     * @throws \Exception
     */
    public function handleTicket() {
        // Redirect the the user if he is already connected
        if ($this->getUserModule()->isConnected()) {
            $this->redirect(Session::getLastURL());
            return;
        }

        $ticket = $this->paramGet('ticket');

        $envConfig = $this->getContainer()->getConfiguration('environment')->read();
        $cla_auth_host = $envConfig->get('cla-auth-host');
        $cla_auth_identifier = $envConfig->get('cla-auth-identifier');

        $url = Text::format(
            "{}/authentification/{}/{}",
            $cla_auth_host,
            $cla_auth_identifier,
            urlencode($ticket)
        );
        $context = stream_context_create(array(
            'http' => array('ignore_errors' => true),
        ));

        $raw = file_get_contents($url, false, $context);
        $response = json_decode($raw, true);

        if (!$response) {
            echo "La réponse du serveur d'authentification est invalide";
            throw HttpException::createFromCode(500);
        }

        if ($response['success']) {
            $username = $response['payload']['username'];
            $userManager = $this->getModelManager('user');
            $user = $userManager->select(['username' => $username], true);

            if ($user) {
                // We already know that user
                $this->getUserModule()->openUserSession($user);

                // Update the user's profile
                $user->promo = $response['payload']['promo'];
                // $user->alumni = $response['payload']['isAlum'];
                try {
                    $userManager = $user->getManager();
                    $userManager->update($user, ['logged_on' => 'NOW()']);
                } catch (\Exception $e) {}

                // Redirect the user back to his content
                $this->redirect(Session::getLastURL());
            } else {

                // That is a new user, we create an new entity
                $user = $userManager->create();
                $user->username = $response['payload']['username'];
                $user->first_name = $response['payload']['firstName'];
                $user->last_name = $response['payload']['lastName'];
                $user->email_centrale = $response['payload']['emailSchool'];
                $user->promo = $response['payload']['promo'];
                $user->alumni = 0; // $response['payload']['isAlum'];

                // Save that entity in our database
                try {
                    $userManager->insert($user, ['created_on' => 'NOW()', 'logged_on' => 'NOW()']);
                } catch (\Exception $e) {
                    $this->log($e, 'login');
                    throw HttpException::createFromCode(500);
                }

                // Eventually open the user session on our website
                $this->getUserModule()->openUserSession($user);

                // Redirect the user back to his content
                $this->redirect(Session::getLastURL());
            }
        }

        echo "La réponse du serveur d'authentification est invalide";
    }

    /**
     * @throws \Exception
     */
    public function logout() {
        $this->getUserModule()->closeUserSession();
        $this->redirect($this->buildRouteUrl('home-index'));
    }
}
