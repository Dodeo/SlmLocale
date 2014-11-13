<?php
/**
 * Copyright (c) 2012-2013 Jurian Sluiman.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the names of the copyright holders nor the names of the
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @author      Jurian Sluiman <jurian@juriansluiman.nl>
 * @copyright   2012-2013 Jurian Sluiman.
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link        http://juriansluiman.nl
 */

namespace SlmLocale\Locale;

use SlmLocale\LocaleEvent;
use SlmLocale\Strategy\StrategyInterface;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\Stdlib\RequestInterface;
use Zend\Stdlib\ResponseInterface;
use Zend\Uri\Uri;

class Detector implements EventManagerAwareInterface
{
    /**
     * Event manager holding the different strategies
     *
     * @var EventManagerInterface
     */
    protected $events;

    /**
     * Default locale
     *
     * @var string
     */
    protected $default;

    /**
     * Optional list of supported locales
     *
     * @var array
     */
    protected $supported;

    public function getEventManager()
    {
        return $this->events;
    }

    public function setEventManager(EventManagerInterface $events)
    {
        $events->setIdentifiers(array(
            __CLASS__,
            get_called_class()
        ));
        $this->events = $events;

        return $this;
    }

    public function addStrategy(StrategyInterface $strategy, $priority = 1)
    {
        $this->getEventManager()->attachAggregate($strategy, $priority);
    }

    public function getDefault()
    {
        return $this->default;
    }

    public function setDefault($default)
    {
        $this->default = $default;
        return $this;
    }

    public function getSupported()
    {
        return $this->supported;
    }

    public function setSupported(array $supported)
    {
        $this->supported = $supported;
        return $this;
    }

    public function hasSupported()
    {
        return (is_array($this->supported) && count($this->supported));
    }

    public function detect(RequestInterface $request, ResponseInterface $response = null)
    {
        $event = new LocaleEvent(LocaleEvent::EVENT_DETECT, $this);
        $event->setRequest($request);
        $event->setResponse($response);

        if ($this->hasSupported()) {
            $event->setSupported($this->getSupported());
        }

        $events  = $this->getEventManager();
        $results = $events->trigger($event, function($r) {
            return is_string($r);
        });

        if ($results->stopped()) {
            $locale = $results->last();
        } else {
            $locale = $this->getFromIP();
            if($locale == null)
            {
                $locale = $this->getDefault();
            }
        }

        //manage all local format from navigator
        $locale = substr($locale, 0,2);


        if ($this->hasSupported() && !in_array($locale, $this->getSupported())) {
            $locale = $this->getFromIP();
            if($locale == null)
            {
                $locale = $this->getDefault();
            }
        }

        // Trigger FOUND event only when a response is given
        if ($response instanceof ResponseInterface) {
            $event->setName(LocaleEvent::EVENT_FOUND);
            $event->setLocale($locale);

            $return = false;
            /**
             * The response will be returned instead of the found locale
             * only in case a strategy returned the response. This is an
             * indication the strategy has updated the response (e.g. with
             * a Location header) and as such, the response must be returned
             * instead of the locale.
             */
            $events->trigger($event, function ($r) use (&$return) {
                if ($r instanceof ResponseInterface) {
                    $return = true;
                }
            });

            if ($return) {
                return $response;
            }
        }

        return $locale;
    }

    public function assemble($locale, $uri)
    {
        $event = new LocaleEvent(LocaleEvent::EVENT_ASSEMBLE, $this);
        $event->setLocale($locale);

        if ($this->hasSupported()) {
            $event->setSupported($this->getSupported());
        }

        if (!$uri instanceof Uri) {
            $uri = new Uri($uri);
        }
        $event->setUri($uri);

        $events  = $this->getEventManager();
        $results = $events->trigger($event);

        if (!$results->stopped()) {
            return $uri;
        }

        return $results->last();
    }

    public function getFromIP()
    {
        $ip =  $_SERVER['REMOTE_ADDR'];
        $countryCode = file_get_contents("http://api.hostip.info/country.php?ip=".$ip);

        switch($countryCode)
        {
            // case "AT":
            // case "CH":
            // case "DE":
            // case "LU":

            //     $lang = "de";
            //     break;

            case "AG":
            case "AI":
            case "AQ":
            case "AS":
            case "AU":
            case "BB":
            case "BW":
            case "CA":
            case "GB":
            case "IE":
            case "KE":
            case "NG":
            case "NZ":
            case "PH":
            case "SG":
            case "US":
            case "ZA":
            case "ZM":
            case "ZW":

                $lang = "en";
                break;

            case "AD":
            case "AR":
            case "BO":
            case "CL":
            case "CO":
            case "CR":
            case "CU":
            case "DO":
            case "EC":
            case "ES":
            case "GT":
            case "HN":
            case "MX":
            case "NI":
            case "PA":
            case "PE":
            case "PR":
            case "PY":
            case "SV":
            case "UY":
            case "VE":

                $lang = "es";
                break;

            case "BE":
            case "FR":
            case "SN":
            case "LU":
            case "BJ":
            case "BF":
            case "CD":
            case "CG":
            case "CI":
            case "GA":
            case "GN":
            case "ML":
            case "MC":
            case "NE":
            case "TG":
            case "BI":
            case "MG":
            case "MR":
            case "TD":

                $lang = "fr";
                break;

            case "IT":

                $lang = "it";
                break;

            case "AW":
            case "NL":

                $lang = "nl";
                break;

            case "AO":
            case "BR":
            case "PT":

                $lang = "pt";
                break;

            default:
                $lang = 'en';
                break;
        }
        return $lang;
    }
}
