<?php

namespace ADT\Routing;

use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\InvalidLinkException;
use Nette\Application\UI\Presenter;

trait LocaleTrait
{
	#[Persistent]
	public ?string $locale = null;

	public function injectLocale(Presenter $presenter, TranslatorInterface $translator)
	{
		$presenter->onStartup[] = function() use ($presenter, $translator) {
			$locale = $this->locale;

			$parameters = $_GET;

			// uzivatel se prepl na jinou lokalizaci pomoci odkazu na strance
			// tuto lokalizaci mu nastavime do cookie, abychom ho odted vzdy presmerovali na tuto lokalizaci,
			// pokud prijde na url, ze ktere neni jasna lokalizace
			if ($presenter->getParameter('switchToLocale')) {
				if (!in_array($presenter->getParameter('switchToLocale'), $translator->getAvailableLocales())) {
					$this->redirectThisWithParameters($presenter, ['switchToLocale' => null] + $parameters);
				}

				$locale = $presenter->getParameter('switchToLocale');

				$this->setLocaleCookie($locale, 3600 * 24 * 14);
			}
			// pokud ma uzivatel lokalizaci jiz ulozenou v cookie,
			// nastavime mu tuto lokalizaci
			elseif (isset($_COOKIE['locale'])) {
				if (!in_array($_COOKIE['locale'], $translator->getAvailableLocales())) {
					$this->setLocaleCookie($locale, -1);
					$this->redirectThisWithParameters($presenter, $parameters);
				}

				$locale = $_COOKIE['locale'];
			}
			// pokud uzivatel dosel na stranky poprve, pokusime se zjistit jazyk prohlizece
			// a podle nej vybrat nejvhodnejsi locale
			else {
				// jestlize je nastaven jazyk prohlizece
				if ($_langHeader = $presenter->getHttpRequest()->getHeader('Accept-Language')) {
					$lang = substr($_langHeader, 0, 2);

					// pokud jsou stranky lokalizovane do jazyka prohlizece,
					// nastavime tuto lokalizaci
					if (in_array($lang, $translator->getAvailableLocales())) {
						$locale = $lang;
					}
					// pokud jazyk prohlizece je slovenstina a stranky jsou lokalizovane do cestiny,
					// nastavime cestinu
					elseif ($lang === 'sk' && in_array('cs', $translator->getAvailableLocales())) {
						$locale = 'cs';
					}
					// pokud jazyk prohlizece je cestina a stranky jsou lokalizovane do slovenstiny,
					// nastavime slovenstinu
					elseif ($lang === 'cs' && in_array('sk', $translator->getAvailableLocales())) {
						$locale = 'sk';
					}
					// pokud nemame stranky lokalizovane do jazyka prohlizece, ale mame je lokalizovane do anglictiny
					// nastavime anglictinu
					elseif (in_array('en', $translator->getAvailableLocales())) {
						$locale = 'en';
					}
				}
				// pokud neni nastaven jazyk prohlizece (napriklad u robota) a neni nastavena zadna vychozi lokalizace,
				// nastavime defaultni lokalizaci
				elseif ($locale === null) {
					$locale = $translator->getDefaultLocale();
				}

				$this->setLocaleCookie($locale, 3600 * 24 * 14);
			}

			// pokud jsme urcili ze nejsme na nejvhodnejsi lokalizaci,
			// presmerujeme
			if ($locale !== $this->locale) {
				$this->redirectThisWithParameters($presenter, ['locale' => $locale, 'originalLocale' => $this->locale] + $parameters);
			}

			// pokud klikneme na lokalizaci, ktera je jiz aktualni
			if ($presenter->getParameter('switchToLocale')) {
				$this->redirectThisWithParameters($presenter, ['switchToLocale' => null] + $parameters);
			}

			$this->locale = $locale;
		};

		$presenter->onRender[] = function() use ($presenter) {
			$presenter->template->locale = $this->locale;
		};
	}

	/**
	 * Presmerovani na 'this' s parametry z URL ($_GET). Parametry prichazi z requestu, takze neplatny parametr
	 * (`?0=x`, `?page=2'`) je chybny pozadavek, ne chyba aplikace: Nette ho pri predani v $args bere jako chybu
	 * odkazu (InvalidLinkException = HTTP 500 a chybovy mail), proto 404 - stejne jako to Nette dela samo pro
	 * neplatny parametr z requestu (InvalidRequestParameterException v Component::redirect()).
	 */
	private function redirectThisWithParameters(Presenter $presenter, array $parameters): void
	{
		try {
			$presenter->redirect('this', $parameters);
		} catch (InvalidLinkException $e) {
			$presenter->error($e->getMessage());
		}
	}

	private function setLocaleCookie(string $value, int $expires): void
	{
		// pouziva se i v ErrorPresenteru, kdy uz ale mohou byt odeslane hlavicky
		if (!headers_sent()) {
			setcookie('locale', $value, time() + $expires, '/');
		}
	}
}
