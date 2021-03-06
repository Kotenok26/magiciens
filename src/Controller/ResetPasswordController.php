<?php

namespace App\Controller;

use App\Classe\Mail;
use App\Entity\ResetPassword;
use App\Entity\User;
use App\Form\ResetPasswordType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class ResetPasswordController extends AbstractController
{
    private $entityManager;

    public function  __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/reset/mot-de-passe-oublie", name="reset_password")
     */

    public function index(Request $request)
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        if ($request->get('email')) {
            $user = $this->entityManager->getRepository(User::class)->findOneByEmail($request->get('email'));

            if($user) {
                // 1 Enregister en base la demande de reset_password avec user, token, createdAt :

                $reset_password = new ResetPassword();
                $reset_password->setUser($user);
                $reset_password->setToken(uniqid());
                $reset_password->setCreatedAt(new \DateTime());
                $this->entityManager->persist($reset_password);
                $this->entityManager->flush();

                //2 Envoyer un Email avec un lien pour mettre à jour le mot de passe :

                $url = $this->generateUrl('update_password', [
                    'token' => $reset_password->getToken()
                ]);

                $content = "Bonjour ".$user->getFirstname()."<br/> Vous avez souhaité réinitialiser votre mot de passe sur le site Les Magiciens du Fouet<br/><br/>";
                $content .= "Afin de terminer la procédure de réinitialisation, merci de cliquer sur ce lien (valable 3h) : <a href='".$url."'>Je souhaite redéfinir mon mot de passe</a>";
                $mail = new Mail();
                $mail->send($user->getEmail(), $user->getFirstname().' '.$user->getLastname(), 'Réinitialisation de votre mot de passe sur Les Magiciens du Fouet', $content);

                $this->addFlash('notice', 'Nous vous avons envoyé un e-mail avec les instructions pour réinitialiser votre mot de passe');

            } else {
                $this->addFlash('notice', 'Cette adresse email est inconnue');
            }
        }

        return $this->render('reset_password/index.html.twig');
    }

    /**
     * @Route("/modifier-mon-mot-de-passe/{token}", name="update_password")
     */
    public function update(Request $request, $token, UserPasswordEncoderInterface $encoder)
    {
      $reset_password = $this->entityManager->getRepository(ResetPassword::class)->findOneByToken($token);

      if (!$reset_password) {
          return $this->redirectToRoute('reset_password');
      }

      // Vérifier si le createdAt = now - 3h, modifier le mot de passe

        $now = new \DateTime();
        if($now > $reset_password->getCreatedAt()->modify('+ 3 hour')) {
          $this->addFlash('notice', 'Votre demande de mot de passe a expiré. Merci de renouveller.');
          return $this->redirectToRoute('reset_password');
        }

        // Rendre une vue confirmez le mdp

        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){
            $new_pwd = $form->get('new_password')->getData();

            // Encodage de mdp :

            $password = $encoder->encodePassword($reset_password->getUser(), $new_pwd);

            $reset_password->getUser()->setPassword($password);

            // Flush en BD :

            $this->entityManager->flush();

            //Redirection de l'utilisateur :
            $this->addFlash('notice', 'Votre mot de passe a bien été mis à jour');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/update.html.twig', [
            'form' => $form->createView()
        ]);
    }

}
