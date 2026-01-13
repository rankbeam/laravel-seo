<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Support;

/**
 * Stop words management for various languages.
 *
 * Stop words are common words that typically don't carry significant
 * meaning and are often excluded from keyword analysis. Examples in
 * English include: "the", "is", "at", "which", "on".
 *
 * ## Supported Languages
 *
 * - English (en) - 200+ words
 * - Italian (it) - 200+ words
 * - German (de) - 200+ words
 * - French (fr) - 200+ words
 * - Spanish (es) - 200+ words
 *
 * ## Usage
 *
 * ```php
 * $stopWords = app(StopWords::class);
 *
 * // Get all stop words for a locale
 * $words = $stopWords->get('en');
 *
 * // Check if a word is a stop word
 * if ($stopWords->isStopWord('the', 'en')) { ... }
 *
 * // Filter stop words from tokens
 * $filtered = $stopWords->removeStopWords(['the', 'quick', 'fox'], 'en');
 * // ["quick", "fox"]
 * ```
 *
 * @see \Fibonoir\LaravelSEO\Support\Tokenizer For text tokenization
 * @see \Fibonoir\LaravelSEO\Services\Analyzer\ContentAnalyzer For usage context
 */
class StopWords
{
    /**
     * Stop words organized by locale.
     *
     * @var array<string, array<int, string>>
     */
    protected array $stopWords = [
        'en' => [
            // Articles
            'a', 'an', 'the',
            // Pronouns
            'i', 'me', 'my', 'myself', 'we', 'our', 'ours', 'ourselves', 'you', 'your',
            'yours', 'yourself', 'yourselves', 'he', 'him', 'his', 'himself', 'she', 'her',
            'hers', 'herself', 'it', 'its', 'itself', 'they', 'them', 'their', 'theirs',
            'themselves', 'what', 'which', 'who', 'whom', 'this', 'that', 'these', 'those',
            // Verbs (be, have, do)
            'am', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had',
            'having', 'do', 'does', 'did', 'doing', 'would', 'should', 'could', 'ought',
            'might', 'must', 'shall', 'will', 'can', 'may',
            // Contractions
            'im', 'youre', 'hes', 'shes', 'its', 'were', 'theyre', 'ive', 'youve', 'weve',
            'theyve', 'id', 'youd', 'hed', 'shed', 'wed', 'theyd', 'ill', 'youll', 'hell',
            'shell', 'well', 'theyll', 'isnt', 'arent', 'wasnt', 'werent', 'hasnt', 'havent',
            'hadnt', 'doesnt', 'dont', 'didnt', 'wont', 'wouldnt', 'shant', 'shouldnt',
            'cant', 'cannot', 'couldnt', 'mustnt', 'lets', 'thats', 'whos', 'whats',
            'heres', 'theres', 'whens', 'wheres', 'whys', 'hows',
            // Prepositions
            'about', 'above', 'across', 'after', 'against', 'along', 'among', 'around',
            'at', 'before', 'behind', 'below', 'beneath', 'beside', 'between', 'beyond',
            'by', 'down', 'during', 'except', 'for', 'from', 'in', 'inside', 'into',
            'near', 'of', 'off', 'on', 'onto', 'out', 'outside', 'over', 'past', 'since',
            'through', 'throughout', 'till', 'to', 'toward', 'under', 'until', 'up',
            'upon', 'with', 'within', 'without',
            // Conjunctions
            'and', 'but', 'or', 'nor', 'for', 'yet', 'so', 'although', 'because', 'since',
            'unless', 'while', 'whereas', 'if', 'then', 'else', 'when', 'where', 'why',
            'how', 'whether', 'either', 'neither', 'both', 'not', 'only',
            // Adverbs
            'again', 'also', 'always', 'anywhere', 'away', 'back', 'else', 'even', 'ever',
            'everywhere', 'far', 'here', 'how', 'however', 'just', 'least', 'less', 'more',
            'most', 'never', 'no', 'not', 'now', 'nowhere', 'often', 'once', 'only',
            'perhaps', 'rather', 'really', 'seldom', 'so', 'some', 'sometimes', 'somewhere',
            'soon', 'still', 'such', 'than', 'then', 'there', 'therefore', 'thus', 'too',
            'very', 'well', 'yet',
            // Determiners
            'all', 'another', 'any', 'each', 'every', 'few', 'many', 'much', 'no', 'other',
            'several', 'some', 'such',
            // Other common words
            'able', 'been', 'being', 'came', 'come', 'comes', 'coming', 'get', 'gets',
            'getting', 'go', 'goes', 'going', 'gone', 'got', 'gotten', 'has', 'have',
            'having', 'just', 'keep', 'kept', 'know', 'known', 'knows', 'let', 'like',
            'look', 'looking', 'looks', 'made', 'make', 'makes', 'making', 'might',
            'move', 'must', 'need', 'new', 'old', 'one', 'ones', 'own', 'put', 'said',
            'same', 'saw', 'say', 'says', 'see', 'seen', 'seem', 'seemed', 'seems',
            'set', 'take', 'taken', 'takes', 'tell', 'tells', 'think', 'thinks', 'thought',
            'told', 'took', 'try', 'turn', 'two', 'use', 'used', 'uses', 'using', 'want',
            'wanted', 'wants', 'way', 'ways', 'went', 'yes',
        ],

        'it' => [
            // Articoli
            'il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'uno', 'una', 'dei', 'degli', 'delle',
            // Preposizioni semplici
            'di', 'a', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra',
            // Preposizioni articolate
            'al', 'allo', 'alla', 'ai', 'agli', 'alle', 'del', 'dello', 'della',
            'dal', 'dallo', 'dalla', 'dai', 'dagli', 'dalle', 'nel', 'nello', 'nella',
            'nei', 'negli', 'nelle', 'sul', 'sullo', 'sulla', 'sui', 'sugli', 'sulle',
            'col', 'coi',
            // Congiunzioni
            'e', 'o', 'ma', 'se', 'perché', 'che', 'quando', 'come', 'dove', 'quindi',
            'però', 'oppure', 'cioè', 'infatti', 'dunque', 'perciò', 'tuttavia', 'anzi',
            'invece', 'mentre', 'sebbene', 'benché', 'purché', 'affinché', 'sicché',
            // Pronomi personali
            'io', 'tu', 'egli', 'ella', 'esso', 'essa', 'noi', 'voi', 'loro', 'essi',
            'esse', 'mi', 'ti', 'ci', 'vi', 'si', 'ne', 'me', 'te', 'lui', 'lei', 'sé',
            // Pronomi possessivi
            'mio', 'mia', 'miei', 'mie', 'tuo', 'tua', 'tuoi', 'tue', 'suo', 'sua',
            'suoi', 'sue', 'nostro', 'nostra', 'nostri', 'nostre', 'vostro', 'vostra',
            'vostri', 'vostre', 'loro',
            // Pronomi dimostrativi e relativi
            'chi', 'cui', 'questo', 'questa', 'questi', 'queste', 'quello', 'quella',
            'quelli', 'quelle', 'codesto', 'stesso', 'medesimo', 'tale', 'quali', 'quale',
            // Verbi ausiliari e comuni
            'essere', 'avere', 'fare', 'dire', 'andare', 'venire', 'dare', 'stare',
            'sono', 'sei', 'è', 'siamo', 'siete', 'hanno', 'ho', 'hai', 'ha', 'abbiamo',
            'avete', 'era', 'ero', 'erano', 'sarà', 'sarebbe', 'stato', 'stata', 'stati',
            'state', 'fatto', 'fatta', 'fatti', 'fatte', 'detto', 'andato', 'venuto',
            // Avverbi
            'non', 'più', 'molto', 'poco', 'tanto', 'troppo', 'tutto', 'niente', 'nulla',
            'sempre', 'mai', 'già', 'ancora', 'ora', 'poi', 'prima', 'dopo', 'sopra',
            'sotto', 'dentro', 'fuori', 'qui', 'qua', 'lì', 'là', 'dove', 'come', 'così',
            'bene', 'male', 'meglio', 'peggio', 'forse', 'quasi', 'circa', 'anche',
            'pure', 'solo', 'proprio', 'appena', 'subito',
            // Aggettivi indefiniti
            'ogni', 'qualche', 'alcuni', 'alcuno', 'nessuno', 'altro', 'certo', 'vario',
            'parecchio', 'diverso', 'alcun', 'alcuna',
            // Numerali comuni
            'uno', 'due', 'tre', 'primo', 'secondo', 'terzo',
        ],

        'de' => [
            // Artikel
            'der', 'die', 'das', 'den', 'dem', 'des', 'ein', 'eine', 'einer', 'einem',
            'einen', 'eines',
            // Pronomen
            'ich', 'du', 'er', 'sie', 'es', 'wir', 'ihr', 'mich', 'dich', 'sich', 'uns',
            'euch', 'mir', 'dir', 'ihm', 'ihnen', 'mein', 'dein', 'sein', 'unser', 'euer',
            // Präpositionen
            'an', 'auf', 'aus', 'bei', 'durch', 'für', 'gegen', 'hinter', 'in', 'mit',
            'nach', 'neben', 'ohne', 'über', 'um', 'unter', 'von', 'vor', 'zu', 'zwischen',
            // Konjunktionen
            'und', 'oder', 'aber', 'denn', 'sondern', 'weil', 'dass', 'wenn', 'als',
            'ob', 'obwohl', 'während', 'bevor', 'nachdem', 'damit', 'sodass', 'falls',
            // Hilfsverben
            'sein', 'haben', 'werden', 'bin', 'bist', 'ist', 'sind', 'seid', 'war',
            'waren', 'gewesen', 'habe', 'hast', 'hat', 'habt', 'hatte', 'hatten',
            'werde', 'wirst', 'wird', 'werdet', 'wurde', 'wurden',
            // Modalverben
            'können', 'müssen', 'sollen', 'wollen', 'dürfen', 'mögen', 'kann', 'muss',
            'soll', 'will', 'darf', 'mag', 'konnte', 'musste', 'sollte', 'wollte',
            // Adverbien
            'auch', 'noch', 'schon', 'immer', 'nie', 'jetzt', 'hier', 'dort', 'dann',
            'nur', 'sehr', 'mehr', 'weniger', 'ganz', 'gar', 'wohl', 'doch', 'ja',
            'nein', 'nicht', 'nichts', 'etwas', 'viel', 'wenig', 'alles', 'alle',
            // Fragewörter
            'wer', 'was', 'wo', 'wie', 'warum', 'wann', 'welcher', 'welche', 'welches',
            // Andere häufige Wörter
            'man', 'kein', 'keine', 'keiner', 'jeder', 'jede', 'jedes', 'dieser', 'diese',
            'dieses', 'jener', 'jene', 'jenes', 'solcher', 'solche', 'solches', 'selbst',
        ],

        'fr' => [
            // Articles
            'le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'au', 'aux',
            // Pronoms
            'je', 'tu', 'il', 'elle', 'on', 'nous', 'vous', 'ils', 'elles', 'me', 'te',
            'se', 'lui', 'leur', 'moi', 'toi', 'soi', 'ce', 'cela', 'ça', 'qui', 'que',
            'quoi', 'dont', 'où', 'lequel', 'laquelle', 'lesquels', 'lesquelles',
            // Possessifs
            'mon', 'ma', 'mes', 'ton', 'ta', 'tes', 'son', 'sa', 'ses', 'notre', 'nos',
            'votre', 'vos', 'leur', 'leurs',
            // Démonstratifs
            'ce', 'cet', 'cette', 'ces', 'celui', 'celle', 'ceux', 'celles',
            // Prépositions
            'à', 'de', 'en', 'dans', 'sur', 'sous', 'avec', 'sans', 'pour', 'par',
            'entre', 'vers', 'chez', 'contre', 'depuis', 'pendant', 'avant', 'après',
            // Conjonctions
            'et', 'ou', 'mais', 'donc', 'or', 'ni', 'car', 'que', 'si', 'comme',
            'quand', 'lorsque', 'puisque', 'parce', 'bien', 'ainsi', 'cependant',
            'pourtant', 'toutefois', 'néanmoins', 'tandis', 'alors',
            // Verbes auxiliaires
            'être', 'avoir', 'suis', 'es', 'est', 'sommes', 'êtes', 'sont', 'ai', 'as',
            'a', 'avons', 'avez', 'ont', 'été', 'eu', 'était', 'étais', 'étaient',
            'avait', 'avais', 'avaient', 'sera', 'serai', 'seront', 'aura', 'auront',
            // Adverbes
            'ne', 'pas', 'plus', 'moins', 'très', 'bien', 'mal', 'aussi', 'encore',
            'toujours', 'jamais', 'déjà', 'souvent', 'parfois', 'ici', 'là', 'où',
            'comment', 'pourquoi', 'quand', 'combien', 'tout', 'tous', 'toute', 'toutes',
            'même', 'autre', 'autres', 'quelque', 'quelques', 'plusieurs', 'peu',
            'beaucoup', 'trop', 'assez', 'rien', 'personne', 'aucun', 'aucune',
            // Autres mots communs
            'fait', 'faire', 'dit', 'dire', 'peut', 'pouvoir', 'veut', 'vouloir',
            'doit', 'devoir', 'faut', 'falloir', 'aller', 'venir', 'voir', 'savoir',
        ],

        'es' => [
            // Artículos
            'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas', 'lo',
            // Pronombres personales
            'yo', 'tú', 'él', 'ella', 'usted', 'nosotros', 'nosotras', 'vosotros',
            'vosotras', 'ellos', 'ellas', 'ustedes', 'me', 'te', 'se', 'nos', 'os',
            'le', 'les', 'lo', 'la', 'los', 'las',
            // Posesivos
            'mi', 'mis', 'tu', 'tus', 'su', 'sus', 'nuestro', 'nuestra', 'nuestros',
            'nuestras', 'vuestro', 'vuestra', 'vuestros', 'vuestras',
            // Demostrativos
            'este', 'esta', 'estos', 'estas', 'ese', 'esa', 'esos', 'esas', 'aquel',
            'aquella', 'aquellos', 'aquellas', 'esto', 'eso', 'aquello',
            // Preposiciones
            'a', 'ante', 'bajo', 'con', 'contra', 'de', 'desde', 'durante', 'en',
            'entre', 'hacia', 'hasta', 'mediante', 'para', 'por', 'según', 'sin',
            'sobre', 'tras',
            // Conjunciones
            'y', 'e', 'o', 'u', 'pero', 'sino', 'mas', 'aunque', 'porque', 'pues',
            'que', 'si', 'como', 'cuando', 'donde', 'mientras', 'apenas', 'tan',
            // Verbos auxiliares
            'ser', 'estar', 'haber', 'tener', 'soy', 'eres', 'es', 'somos', 'sois',
            'son', 'estoy', 'estás', 'está', 'estamos', 'estáis', 'están', 'he',
            'has', 'ha', 'hemos', 'habéis', 'han', 'tengo', 'tienes', 'tiene',
            'tenemos', 'tenéis', 'tienen', 'sido', 'estado', 'habido', 'tenido',
            // Adverbios
            'no', 'sí', 'muy', 'más', 'menos', 'ya', 'todavía', 'aún', 'siempre',
            'nunca', 'también', 'tampoco', 'aquí', 'ahí', 'allí', 'así', 'bien',
            'mal', 'mucho', 'poco', 'bastante', 'demasiado', 'casi', 'solo', 'además',
            // Otros
            'todo', 'toda', 'todos', 'todas', 'otro', 'otra', 'otros', 'otras',
            'mismo', 'misma', 'mismos', 'mismas', 'cada', 'algún', 'alguna', 'algunos',
            'algunas', 'ningún', 'ninguna', 'varios', 'varias', 'cualquier', 'cualquiera',
        ],
    ];

    /**
     * Get stop words for a locale.
     *
     * @param string $locale The locale code (e.g., 'en', 'it', 'de')
     * @return array<int, string> Array of stop words
     *
     * @example
     * ```php
     * $words = $stopWords->get('en'); // English stop words
     * $words = $stopWords->get('it'); // Italian stop words
     * ```
     */
    public function get(string $locale = 'en'): array
    {
        $normalizedLocale = explode('_', str_replace('-', '_', $locale))[0];

        return $this->stopWords[$normalizedLocale] ?? $this->stopWords['en'];
    }

    /**
     * Check if a word is a stop word.
     *
     * @param string $word The word to check
     * @param string $locale The locale code
     * @return bool True if the word is a stop word
     *
     * @example
     * ```php
     * $stopWords->isStopWord('the', 'en'); // true
     * $stopWords->isStopWord('cat', 'en'); // false
     * ```
     */
    public function isStopWord(string $word, string $locale = 'en'): bool
    {
        $stopWordList = $this->get($locale);

        return in_array(mb_strtolower($word), $stopWordList, true);
    }

    /**
     * Remove stop words from an array of tokens.
     *
     * @param array<int, string> $tokens The tokens to filter
     * @param string $locale The locale code
     * @return array<int, string> Tokens with stop words removed
     *
     * @example
     * ```php
     * $filtered = $stopWords->removeStopWords(['the', 'quick', 'brown', 'fox'], 'en');
     * // ["quick", "brown", "fox"]
     * ```
     */
    public function removeStopWords(array $tokens, string $locale = 'en'): array
    {
        $stopWordList = $this->get($locale);

        return array_values(array_filter(
            $tokens,
            fn ($token) => ! in_array(mb_strtolower($token), $stopWordList, true)
        ));
    }

    /**
     * Add custom stop words for a locale.
     *
     * @param string $locale The locale code
     * @param array<int, string> $words The words to add
     */
    public function addStopWords(string $locale, array $words): void
    {
        $normalizedLocale = explode('_', $locale)[0];

        if (! isset($this->stopWords[$normalizedLocale])) {
            $this->stopWords[$normalizedLocale] = [];
        }

        $this->stopWords[$normalizedLocale] = array_unique(array_merge(
            $this->stopWords[$normalizedLocale],
            array_map('mb_strtolower', $words)
        ));
    }

    /**
     * Get available locales.
     *
     * @return array<int, string> List of supported locales
     */
    public function getAvailableLocales(): array
    {
        return array_keys($this->stopWords);
    }

    /**
     * Get the count of stop words for a locale.
     *
     * @param string $locale The locale code
     * @return int Number of stop words
     */
    public function count(string $locale = 'en'): int
    {
        return count($this->get($locale));
    }
}
