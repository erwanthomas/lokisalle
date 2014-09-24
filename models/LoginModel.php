<?php

class LoginModel extends Model
{
    /**
     * Méthode de connexion d'un utilisateur
     * -------------------------------------
     * Teste la validité d'un couple username/password envoyé via POST
     * - Si invalide, renvoie d'une chaîne de caractères indiquant
     * brièvement le message d'erreur.
     * - Si valide, alors renvoie le booléen true et ajoute à la session
     * courante les infos de l'utilisateur
     */
    public function login()
    {
        // Test 0: les données sont-elles présentes ?
        if ( !isset($_POST['username']) ||
              empty($_POST['username']) ||
             !isset($_POST['password']) ||
              empty($_POST['password'])
        ) {
            return 'empty_fields';
        }

        // Test 1: l'utilisateur existe-t-il ?
        $clean_username = $this->db->real_escape_string($_POST['username']);

        $result = $this->db->query('SELECT id_membre,
                                     pseudo,
                                     mdp,
                                     nom,
                                     email,
                                     sexe,
                                     ville,
                                     cp,
                                     adresse,
                                     statut
                                FROM membres
                                WHERE pseudo="' . $clean_username . '"');

        if ( $result->num_rows === 0 ) {
            return 'unknown_user';
        }

        // TEST 2: le mot de passe correspond-il à l'username demandé ?
        $user = $result->fetch_assoc(); // NOTE: fetch_assoc() ne devrait renvoyer qu'une ligne!

        if ( password_verify($_POST['password'], $user['mdp']) ) {
            // le mot de passe est bon, alors on ajoute l'utilisateur à la session
            foreach ($user as $key => $value) {
                if ( $key != 'mdp' ) {
                    $user_data[$key] = $value;
                }
            }
            // on ajoute une méta-donnée pas nécessaire, mais pratique
            $user_data['logged_in'] = true;

            // Enregistrement dans la session PHP de l'utilisateur
            Session::set('user', $user_data);

            return true; // pour indiquer au contrôleur le succès de la connexion

        } else {
            return 'wrong_password';
        }

        // par défaut, la connexion doit échouer
        return false;
    }

    /**
     * Méthode de déconnexion d'un utilisateur
     * ---------------------------------------
     * Une connexion entraînant l'inscription dans la session d'un tableau 'user',
     * la déconnexion doit donc entraîner au minimum la destruction de ce tableau.
     * Ici on est plus radicale et on détruit toutes les données de la session.
     */
    public function logout()
    {
        if ( Session::userIsLoggedIn() ) {

            Session::wipe_all();
            return 'Déconnexion effectuée';
        }

        return 'Pas de connexion active. Voulez-vous vous connecter ?';
    }
}