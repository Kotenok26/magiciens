<?php

namespace App\Controller;

use App\Entity\User;
use App\Classe\Mail;
use App\Form\RegisterType;
#use http\Env\Request;
#use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;


class RegisterController extends AbstractController
{
    private $entityManager;
    public function __construct(EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
    }
    /**
     * @Route("/inscription", name="register")
     */
    public function index(Request $request, UserPasswordEncoderInterface $encoder)
    {
        $notification = null;

        $user = new User();
        $form = $this->createForm(RegisterType::class, $user);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            $user = $form->getData();

            $search_email = $this->entityManager->getRepository(User::class)->findOneByEmail($user->getEmail());

            if (!$search_email) {
                $password = $encoder->encodePassword($user, $user->getPassword());
                $user->setPassword($password);

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                $mail = new Mail();
                $content = "Bonjour ".$user->getFirstname().",<br/>Bienvenue sur la première boutique dédiée au made in France !<br><br/>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Facere laudantium neque odit ratione! Alias amet asperiores, commodi, consectetur, dolor enim eos est eum itaque laboriosam libero magnam magni nostrum numquam odit officiis provident quia repellendus suscipit veniam! Accusantium, adipisci asperiores aspernatur at, cum minima minus nesciunt perferendis, rem tempora veritatis.";
                $mail->send($user->getEmail(), $user->getFirstname(), 'Bienvenue sur Les Magiciens du Fouet', $content);


                $notification = "Votre inscription s'est correctement déroulée. Vous pouvez dès à présent vous connecter à votre compte";
            } else {
                $notification = "L'email que vous avez renseigné existe déjà";
            }

        }

        return $this->render('register/index.html.twig', [
            'form' => $form->createView(),
            'notification' => $notification
        ]);
    }
}
