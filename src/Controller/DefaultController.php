<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends HgvController
{
  public function index(): Response
  {
    return $this->render('default/index.html.twig', [
      'controller_name' => 'DefaultController'
  ]);
  }

  public function abbreviation(): Response
  {
    return $this->render('default/abbreviation.html.twig');
  }

  public function help($topic = '', $language = ''): Response
  {
    return $this->render('default/help' . ucfirst($topic) . ucfirst($language) . '.html.twig');
  }

  public function introduction(): Response
  {
    return $this->render('default/introduction.html.twig');
  }

  public function publication(): Response
  {
    return $this->render('default/publication.html.twig');
  }

  public function feedback(): Response
  {
    $to = 'Dieter Hagedorn <dieter.hagedorn@urz.uni-heidelberg.de>, James Cowey <james.cowey@urz.uni-heidelberg.de>, Carmen Lanz <carmen.lanz@zaw.uni-heidelberg.de>';
    $to = 'James Cowey <james.cowey@urz.uni-heidelberg.de>, Carmen Lanz <carmen.lanz@zaw.uni-heidelberg.de>';
    $name    = $this->smcf_filter($this->getParameter('name'));
    $email   = $this->smcf_filter($this->getParameter('email'));
    $subject = $this->smcf_filter($this->getParameter('subject'));
    $message = $this->smcf_filter($this->getParameter('message'));

    $data = array('name' => $name, 'email' => $email, 'subject' => $subject, 'message' => $message);
    
    //return new Response(json_encode(array('success' => false, 'error' => 'Leider konnte Ihre Nachricht nicht gesendet werden. Bitte versuchen Sie es später erneut oder kontaktieren Sie uns direkt per <a href="mailto:dieter.hagedorn@urz.uni-heidelberg.de,james.cowey@urz.uni-heidelberg.de,carmen.lanz@zaw.uni-heidelberg.de?subject=' . $subject . '&body=' . $message . '">E-Mail</a>.', 'data' => $data)));

    if($this->smcf_validate_email($email)){
      // Set and wordwrap message body
      $body = wordwrap($message, 70);

      // Build header
      $headers = 'From: ' . (count($name) ? $name . '<' . $email . '>' : $email) . "\n" . "X-Mailer: PHP/SimpleModalContactForm";

      // UTF-8
      if (function_exists('mb_encode_mimeheader')) {
        $subject = mb_encode_mimeheader($subject, "UTF-8", "B", "\n");
      }
      else {
        // you need to enable mb_encode_mimeheader or risk 
        // getting emails that are not UTF-8 encoded
      }
      $headers .= "MIME-Version: 1.0\n";
      $headers .= "Content-type: text/plain; charset=utf-8\n";
      $headers .= "Content-Transfer-Encoding: quoted-printable\n";

      // Send email
      if(@mail($to, $subject, $body, $headers)){
        return new Response(json_encode(array('success' => true, 'data' => $data)));
      }
    }

    return new Response(json_encode(array('success' => false, 'error' => 'Leider konnte Ihre Nachricht nicht gesendet werden. Bitte versuchen Sie es später erneut oder kontaktieren Sie uns direkt per <a href="mailto:dieter.hagedorn@urz.uni-heidelberg.de,james.cowey@urz.uni-heidelberg.de,carmen.lanz@zaw.uni-heidelberg.de?subject=' . $subject . '&body=' . $message . '">E-Mail</a>.', 'data' => $data)));
  }

  private function smcf_filter($value) {
    $pattern = array("/\n/","/\r/","/content-type:/i","/to:/i", "/from:/i", "/cc:/i");
    $value = preg_replace($pattern, '', $value);
    return $value;
  }
  
  private function smcf_validate_email($email) {
    $at = strrpos($email, "@");

    // Make sure the at (@) sybmol exists and  
    // it is not the first or last character
    if ($at && ($at < 1 || ($at + 1) == strlen($email)))
      return false;

    // Make sure there aren't multiple periods together
    if (preg_match("/(\.{2,})/", $email))
      return false;

    // Break up the local and domain portions
    $local = substr($email, 0, $at);
    $domain = substr($email, $at + 1);

    // Check lengths
    $locLen = strlen($local);
    $domLen = strlen($domain);
    if ($locLen < 1 || $locLen > 64 || $domLen < 4 || $domLen > 255)
      return false;

    // Make sure local and domain don't start with or end with a period
    if (preg_match("/(^\.|\.$)/", $local) || preg_match("/(^\.|\.$)/", $domain))
      return false;

    // Check for quoted-string addresses
    // Since almost anything is allowed in a quoted-string address,
    // we're just going to let them go through
    if (!preg_match('/^"(.+)"$/', $local)) {
      // It's a dot-string address...check for valid characters
      if (!preg_match('/^[-a-zA-Z0-9!#$%*\/?|^{}`~&\'+=_\.]*$/', $local))
        return false;
    }

    // Make sure domain contains only valid characters and at least one period
    if (!preg_match("/^[-a-zA-Z0-9\.]*$/", $domain) || !strpos($domain, "."))
      return false; 

    return true;
  }

}
