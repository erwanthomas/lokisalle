<?php

class Panier extends Model
{
    const TVA = 19.6;
    const PRODUIT_ALREADY_SET = 'Ce produit est déjà présent dans le panier.';
    const PROMO_ALREADY_SET = 'Cette promotion a déjà été appliquée au panier.';

    protected $produits   = [];
    protected $promotions = [];

    /**
     * Initialise un panier (avec éventuellement des données de la session)
     *
     * @param mysqli $db     le connecteur à la base de données
     * @param array  $panier un tableau associatif listant les ID des produits et des promotions du panier en session
     */
    public function __construct( $db, $panier = [] )
    {
        parent::__construct($db);

        if ( !empty($panier['produits']) ) {
            $this->setProduits( $panier['produits'] );
        }

        if ( !empty($panier['promotions']) ) {
            $this->setPromotions( $panier['promotions'] );
        }
    }

    /*
     * Les deux méthodes suivantes prennent un tableau d'IDs en argument et charge dans
     * le panier depuis la BDD les dernières infos des éléments auxquels ces IDs renvoient.
     */
    protected function setProduits( $array )
    {
        $collector = new ProduitCollector($this->db);

        foreach ( $array as $id ) {
            $produit = $collector->getSingleProduit( $id, 'withPromo' );
            if ( !empty($produit) ) {
                $produit = $produit[0];
                $this->produits[$produit['produitID']] = $produit;
            }
        }
    }

    protected function setPromotions( $array )
    {
        $collector = new PromotionCollector($this->db);

        foreach ( $array as $id ) {
            $promotion = $collector->getPromotions( $id );
            if ( !empty($promotion) ) {
                $promotion = $promotion[0];
                $this->promotions[$promotion['promoId']] = $promotion;
            }
        }
    }

    /**
     * Vide le panier courant (l'objet comme les données en session)
     */
    public function clear()
    {
        Session::delete('panier');
        $this->produits = [];
        $this->promotions = [];
    }

    /**
     * Calcule et renvoie le montant total HT du panier, avant promotions
     * @return int le montant total du panier
     */
    public function calcProduitsTotal()
    {
        $total = 0;
        foreach ( $this->produits as $produit ) {
            $total += $produit['produitPrix'];
        }

        return ($total < 0) ? 0 : $total;
    }

    /**
     * Calcule et renvoie le montant total de la réduction due aux promos
     * @return int le montant total de promotion appliquable
     */
    public function calcPromotionsTotal()
    {
        $appliquables = [];
        $reductionTotal = 0;

        /* On teste pour chaque produit si une promotion s'applique à lui.
         * Si c'est le cas, alors on retient cette promo dans un tableau temporaire.
         */
        foreach ( $this->produits as $produit ) {
            foreach ( $this->promotions as $promo ) {
                if ( $promo['promoId'] == $produit['promoId'] ) {
                    $appliquables[ $promo['promoId'] ] = $promo['promoReduction'];
                }
            }
        }

        /* on connaît maintenant toutes les promos appliquables, on en calcule la somme */
        foreach ( $appliquables as $reduction ) {
            $reductionTotal += $reduction;
        }

        return $reductionTotal;
    }

    /**
     * Calcule et renvoie le montant total de la TVA à appliquer au panier
     * @param  int   $total le montant total HT
     * @return float        le montant de la TVA à ajouter au total HT
     */
    public function calcTVA( $total )
    {
        return $total * self::TVA/100;
    }

    /**
     * Ajoute au panier un produit à condition qu'il n'y soit pas déjà présent
     * @param Produit $produit le produit à ajouter au panier
     */
    public function addProduit(Produit $produit)
    {
        if ( isset( $this->produits[$produit->getID()] ) ) {
            throw new Exception(self::PRODUIT_ALREADY_SET);
        }

        $this->setProduits( [$produit->getID()] );
    }

    /**
     * Ajoute au panier une promotion à condition qu'elle n'y soit pas déjà présente
     * @param Promotion $promotion la promotion à ajouter au panier
     */
    public function addPromotion(Promotion $promotion)
    {
        if ( isset( $this->promotions[$promotion->getId()] ) ) {
            throw new Exception(self::PROMO_ALREADY_SET);
        }

        $this->setPromotions( [$promotion->getId()] );
    }

    /**
     * Retire un produit du panier
     * @param  Produit $produit le produit à retirer
     */
    public function remProduit(Produit $produit)
    {
        unset( $this->produits[$produit->getID()] );
    }

    /**
     * Renvoie les données du panier courant nécessaires et suffisantes pour pouvoir
     * instancier un nouveau panier sur la base de celui qui exécute cette méthode
     *
     * @return array le panier tel qu'il pourra être fourni au constructeur de Panier
     */
    public function toSession()
    {
        return [
            'produits' => array_keys($this->produits),
            'promotions' => array_keys($this->promotions)
        ];
    }

    /**
     * Renvoie les données du panier telles qu'elles pourront être exploitées dans un vue
     *
     * @return array le panier courant
     */
    public function toDisplay()
    {
        $totalHT = $this->calcProduitsTotal();

        return [
            'produits' => $this->produits,
            'promotions' => $this->promotions,
            'total' => $totalHT,
            'totalPromo' => $this->calcPromotionsTotal(),
            'tva' => $this->calcTVA($totalHT)
        ];
    }
}
