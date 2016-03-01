<?php
/*
 * Copyright 2016 Google
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Lullabot\AMP\Pass;

use QueryPath\DOMQuery;

use Lullabot\AMP\ActionTakenLine;
use Lullabot\AMP\ActionTakenType;

/**
 * Class InstagramTransformPass
 * @package Lullabot\AMP\Pass
 */
class InstagramTransformPass extends BasePass
{
    function pass()
    {
        $all_instagram = $this->q->top()->find('blockquote[class="instagram-media"]');
        /** @var DOMQuery $el */
        foreach ($all_instagram as $el) {
            /** @var \DOMElement $dom_el */
            $dom_el = $el->get(0);
            $lineno = $dom_el->getLineNo();
            $shortcode = $this->getShortcode($el);
            $context_string = $this->getContextString($dom_el);
            $instagram_script_tag = $this->getInstagramScriptTag($el);

            // Dealing with height and width is going to be tricky
            // https://github.com/ampproject/amphtml/blob/master/extensions/amp-instagram/amp-instagram.md
            // @todo make this smarter
            /** @var \DOMElement $new_dom_el */
            $el->after("<amp-instagram layout=\"responsive\" width=\"400\" height=\"600\" data-shortcode=\"$shortcode\"></amp-instagram>");
            $new_dom_el = $el->get(0);

            // Remove the blockquote, its children and the instagram script tag that follows after the blockquote
            $el->removeChildren()->remove();
            if (!empty($instagram_script_tag)) {
                $instagram_script_tag->remove();
                $this->addActionTaken(new ActionTakenLine('blockquote.instagram-media (with associated script tag)', ActionTakenType::INSTAGRAM_CONVERTED, $lineno, $context_string));
            } else {
                $this->addActionTaken(new ActionTakenLine('blockquote.instagram-media', ActionTakenType::INSTAGRAM_CONVERTED, $lineno, $context_string));
            }

            $this->context->addLineAssociation($new_dom_el, $lineno);
        }

        return $this->warnings;
    }

    /**
     * Get reference to associated <script> tag, if any.
     *
     * @param DOMQuery $el
     * @return DOMQuery|null
     */
    protected function getInstagramScriptTag(DOMQuery $el)
    {
        $script_tags = $el->nextAll('script');
        $instagram_script_tag = null;
        /** @var DOMQuery $script_tag */
        foreach ($script_tags as $script_tag) {
            if (!empty($script_tag) && preg_match('&(*UTF8)instagram.com/.*/embeds.js&i', $script_tag->attr('src'))) {
                $instagram_script_tag = $script_tag;
                break;
            }
        }

        return $instagram_script_tag;
    }

    /**
     * Get instagram shortcode from the instagram embed code
     */
    protected function getShortcode(DOMQuery $el)
    {
        $links = $el->find('a');
        /** @var DOMQuery $link */
        $shortcode = '';
        // Get the shortcode from the first <a> tag that matches regex and exit
        foreach ($links as $link) {
            $href = $link->attr('href');
            $matches = [];
            if (preg_match('&(*UTF8)instagram.com/p/([^/]+)/?&i', $href, $matches)) {
                if (!empty($matches[1])) {
                    $shortcode = $matches[1];
                    break;
                }
            }
        }

        return $shortcode;
    }
}
