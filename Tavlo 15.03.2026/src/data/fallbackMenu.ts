// Fallback menu data that matches the backend seed data exactly
export const FALLBACK_MENU = {
  categories: [
    { id: 'appetizers', name: 'Appetizers' },
    { id: 'salads', name: 'Salads' },
    { id: 'mains', name: 'Mains' },
    { id: 'desserts', name: 'Desserts' },
    { id: 'drinks', name: 'Drinks' }
  ],
  items: [
    {
      id: 'item_1',
      name: 'Bruschetta al Pomodoro',
      category: 'appetizers',
      price: 8.5,
      description: 'Grilled bread rubbed with garlic and topped with fresh tomatoes, basil, and extra virgin olive oil.',
      calories: 180,
      nutrition: {
        calories: 180,
        protein: 4,
        carbs: 22,
        fat: 8
      },
      dietary: ['vegetarian'],
      allergens: ['gluten'],
      badges: ['vegetarian', 'most-ordered'],
      orderedCount: 342,
      rating: 4.5,
      reviewCount: 28,
      image: 'https://images.unsplash.com/photo-1558679582-4d81ce75993a?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxicnVzY2hldHRhJTIwdG9tYXRvfGVufDF8fHx8MTc2MzkwMzM0MHww&ixlib=rb-4.1.0&q=80&w=1080',
      reviews: [
        {
          name: 'Maria L.',
          rating: 5,
          comment: 'Fresh and delicious! Perfect start to our meal.',
          date: '2025-11-12'
        },
        {
          name: 'Thomas K.',
          rating: 4,
          comment: 'Good but could use more garlic.',
          date: '2025-11-08'
        }
      ],
      modifiers: [
        { name: 'Extra Garlic', price: 1.0 },
        { name: 'Grilled Bread', price: 0.5 }
      ],
      paidAddons: [
        { name: 'Burrata Cheese', price: 3.5 },
        { name: 'Prosciutto', price: 2.5 }
      ],
      freeAddons: ['Extra Basil', 'Balsamic Glaze', 'Black Pepper'],
      removableItems: ['Tomatoes', 'Garlic', 'Basil'],
      vatRate: 10,
      translations: {
        de: {
          name: 'Bruschetta al Pomodoro',
          description: 'Geröstetes Brot mit Knoblauch eingerieben und mit frischen Tomaten, Basilikum und extra nativem Olivenöl belegt.',
          paidAddons: ['Burrata-Käse', 'Prosciutto'],
          freeAddons: ['Extra Basilikum', 'Balsamico-Glasur', 'Schwarzer Pfeffer'],
          removableItems: ['Tomaten', 'Knoblauch', 'Basilikum']
        },
        it: {
          name: 'Bruschetta al Pomodoro',
          description: 'Pane grigliato strofinato con aglio e condito con pomodori freschi, basilico e olio extra vergine di oliva.',
          paidAddons: ['Burrata', 'Prosciutto'],
          freeAddons: ['Basilico Extra', 'Glassa di Aceto Balsamico', 'Pepe Nero'],
          removableItems: ['Pomodori', 'Aglio', 'Basilico']
        },
        fr: {
          name: 'Bruschetta al Pomodoro',
          description: 'Pain grillé frotté à l\'ail et garni de tomates fraîches, basilic et huile d\'olive extra vierge.',
          paidAddons: ['Fromage Burrata', 'Prosciutto'],
          freeAddons: ['Basilic Supplémentaire', 'Glaçage Balsamique', 'Poivre Noir'],
          removableItems: ['Tomates', 'Ail', 'Basilic']
        },
        ar: {
          name: 'بروسكيتا بالطماطم',
          description: 'خبز محمص مدهون بالثوم ومغطى بالطماطم الطازجة والريحان وزيت الزيتون البكر الممتاز.',
          paidAddons: ['جبن بوراتا', 'بروشوتو'],
          freeAddons: ['ريحان إضافي', 'صوص بلسمي', 'فلفل أسود'],
          removableItems: ['طماطم', 'ثوم', 'ريحان']
        },
        tr: {
          name: 'Bruschetta al Pomodoro',
          description: 'Sarımsakla ovulmuş ızgara ekmek, taze domates, fesleğen ve sızma zeytinyağı ile.',
          paidAddons: ['Burrata Peyniri', 'Prosciutto'],
          freeAddons: ['Ekstra Fesleğen', 'Balsamik Sos', 'Siyah Biber'],
          removableItems: ['Domates', 'Sarımsak', 'Fesleğen']
        },
        zh: {
          name: '番茄罗勒意式烤面包',
          description: '烤面包抹上大蒜，配以新鲜番茄、罗勒和特级初榨橄榄油。',
          paidAddons: ['布拉塔奶酪', '意大利火腿'],
          freeAddons: ['额外罗勒', '香醋酱', '黑胡椒'],
          removableItems: ['番茄', '大蒜', '罗勒']
        },
        ja: {
          name: 'トマトのブルスケッタ',
          description: 'ガーリックを擦り込んだグリルパンに、フレッシュトマト、バジル、エクストラバージンオリーブオイルをトッピング。',
          paidAddons: ['ブラータチーズ', 'プロシュート'],
          freeAddons: ['エクストラバジル', 'バルサミコソース', '黒コショウ'],
          removableItems: ['トマト', 'ガーリック', 'バジル']
        },
        sr: {
          name: 'Брускета са парадајзом',
          description: 'Пржени хлеб утрљан белим луком и прекривен свежим парадајзом, босиљком и екстра девичанским маслиновим уљем.',
          paidAddons: ['Бурата сир', 'Прошуто'],
          freeAddons: ['Додатни босиљак', 'Балсамико глазура', 'Црни бибер'],
          removableItems: ['Парадајз', 'Бели лук', 'Босиљак']
        },
        cs: {
          name: 'Bruschetta al Pomodoro',
          description: 'Grilovaný chléb potřený česnekem a pokrytý čerstvými rajčaty, bazalkou a extra panenským olivovým olejem.',
          paidAddons: ['Burrata sýr', 'Prosciutto'],
          freeAddons: ['Extra bazalka', 'Balsamikový dresing', 'Černý pepř'],
          removableItems: ['Rajčata', 'Česnek', 'Bazalka']
        },
        es: {
          name: 'Bruschetta al Pomodoro',
          description: 'Pan tostado frotado con ajo y cubierto con tomates frescos, albahaca y aceite de oliva virgen extra.',
          paidAddons: ['Queso Burrata', 'Prosciutto'],
          freeAddons: ['Albahaca Extra', 'Glaseado Balsámico', 'Pimienta Negra'],
          removableItems: ['Tomates', 'Ajo', 'Albahaca']
        }
      }
    },
    {
      id: 'item_2',
      name: 'Caprese Salad',
      category: 'salads',
      price: 12.0,
      description: 'Fresh mozzarella, tomatoes, and basil drizzled with balsamic glaze.',
      calories: 220,
      nutrition: {
        calories: 220,
        protein: 12,
        carbs: 8,
        fat: 16
      },
      dietary: ['vegetarian', 'gluten-free'],
      allergens: ['dairy'],
      badges: ['vegetarian', 'chefs-pick'],
      orderedCount: 287,
      rating: 4.8,
      reviewCount: 45,
      image: 'https://images.unsplash.com/photo-1595587870672-c79b47875c6a?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxjYXByZXNlJTIwc2FsYWR8ZW58MXx8fHwxNzYzOTI4NDk2fDA&ixlib=rb-4.1.0&q=80&w=1080',
      reviews: [
        {
          name: 'Sophie M.',
          rating: 5,
          comment: 'Amazing mozzarella quality! Best caprese in Vienna.',
          date: '2025-11-15'
        },
        {
          name: 'Marco V.',
          rating: 5,
          comment: 'Authentic Italian taste. Highly recommend!',
          date: '2025-11-10'
        }
      ],
      vatRate: 10,
      translations: {
        de: {
          name: 'Caprese Salat',
          description: 'Frischer Mozzarella, Tomaten und Basilikum mit Balsamico-Glasur beträufelt.'
        },
        it: {
          name: 'Insalata Caprese',
          description: 'Mozzarella fresca, pomodori e basilico conditi con glassa di aceto balsamico.'
        },
        fr: {
          name: 'Salade Caprese',
          description: 'Mozzarella fraîche, tomates et basilic arrosés de glaçage balsamique.'
        },
        ar: {
          name: 'سلطة كابريزي',
          description: 'موزاريلا طازجة وطماطم وريحان مع صوص بلسمي.'
        },
        tr: {
          name: 'Caprese Salata',
          description: 'Taze mozzarella, domates ve fesleğen, balsamik sos ile.'
        },
        zh: {
          name: '卡布里沙拉',
          description: '新鲜马苏里拉奶酪、番茄和罗勒，淋上香醋酱。'
        },
        ja: {
          name: 'カプレーゼサラダ',
          description: 'フレッシュモッツァレラ、トマト、バジルにバルサミコソースをかけて。'
        },
        sr: {
          name: 'Капрезе салата',
          description: 'Свежа моцарела, парадајз и босиљак прелив балсамико глазуром.'
        },
        cs: {
          name: 'Caprese salát',
          description: 'Čerstvá mozzarella, rajčata a bazalka polévaná balsamikovou glazurou.'
        },
        es: {
          name: 'Ensalada Caprese',
          description: 'Mozzarella fresca, tomates y albahaca rociados con glaseado balsámico.'
        }
      }
    },
    {
      id: 'item_truffle',
      name: 'Truffle Mushroom Risotto',
      category: 'mains',
      price: 22.0,
      description: 'Creamy Arborio rice with wild mushrooms, black truffle, and Parmigiano-Reggiano.',
      calories: 520,
      nutrition: {
        calories: 520,
        protein: 12,
        carbs: 68,
        fat: 22
      },
      dietary: ['vegetarian', 'gluten-free'],
      allergens: ['dairy'],
      badges: ['chefs-pick', 'most-ordered'],
      orderedCount: 445,
      rating: 4.9,
      reviewCount: 78,
      image: 'https://images.unsplash.com/photo-1723476654474-77baaeb27012?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxyaXNvdHRvJTIwbXVzaHJvb218ZW58MXx8fHwxNzYzOTE3NzkwfDA&ixlib=rb-4.1.0&q=80&w=1080',
      reviews: [
        {
          name: 'Sarah M.',
          rating: 5,
          comment: 'Absolutely divine! The truffle flavor is perfect.',
          date: '2025-11-10'
        },
        {
          name: 'James K.',
          rating: 4,
          comment: 'Great dish, very creamy and flavorful.',
          date: '2025-11-08'
        }
      ],
      vatRate: 10,
      paidAddons: [
        { name: 'Extra Truffle Shavings', price: 5.0 },
        { name: 'Grilled Chicken Breast', price: 6.5 },
        { name: 'Extra Parmesan', price: 2.0 },
        { name: 'Sautéed Shrimp', price: 7.5 }
      ],
      freeAddons: [
        'Extra Herbs',
        'Lemon Wedge',
        'Black Pepper',
        'Chili Flakes'
      ],
      removableItems: [
        'Mushrooms',
        'Truffle Oil',
        'Parmesan'
      ],
      translations: {
        de: {
          name: 'Trüffel-Pilz-Risotto',
          description: 'Cremiger Arborio-Reis mit Waldpilzen, schwarzem Trüffel und Parmigiano-Reggiano.',
          paidAddons: ['Extra Trüffelspäne', 'Gegrillte Hähnchenbrust', 'Extra Parmesan', 'Sautierte Garnelen'],
          freeAddons: ['Extra Kräuter', 'Zitronenscheibe', 'Schwarzer Pfeffer', 'Chiliflocken'],
          removableItems: ['Pilze', 'Trüffelöl', 'Parmesan']
        },
        it: {
          name: 'Risotto ai Funghi e Tartufo',
          description: 'Riso Arborio cremoso con funghi selvatici, tartufo nero e Parmigiano-Reggiano.',
          paidAddons: ['Scaglie di Tartufo Extra', 'Petto di Pollo Grigliato', 'Parmigiano Extra', 'Gamberetti Saltati'],
          freeAddons: ['Erbe Extra', 'Spicchio di Limone', 'Pepe Nero', 'Peperoncino'],
          removableItems: ['Funghi', 'Olio al Tartufo', 'Parmigiano']
        },
        fr: {
          name: 'Risotto aux Champignons et Truffe',
          description: 'Riz Arborio crémeux avec champignons sauvages, truffe noire et Parmigiano-Reggiano.',
          paidAddons: ['Copeaux de Truffe Supplémentaires', 'Blanc de Poulet Grillé', 'Parmesan Supplémentaire', 'Crevettes Sautées'],
          freeAddons: ['Herbes Supplémentaires', 'Quartier de Citron', 'Poivre Noir', 'Piment'],
          removableItems: ['Champignons', 'Huile de Truffe', 'Parmesan']
        },
        es: {
          name: 'Risotto de Trufa y Hongos',
          description: 'Arroz Arborio cremoso con hongos silvestres, trufa negra y Parmigiano-Reggiano.',
          paidAddons: ['Virutas de Trufa Extra', 'Pechuga de Pollo a la Parrilla', 'Parmesano Extra', 'Camarones Salteados'],
          freeAddons: ['Hierbas Extra', 'Rodaja de Limón', 'Pimienta Negra', 'Hojuelas de Chile'],
          removableItems: ['Hongos', 'Aceite de Trufa', 'Parmesano']
        }
      }
    },
    {
      id: 'item_salmon',
      name: 'Grilled Salmon Fillet',
      category: 'mains',
      price: 24.5,
      description: 'Fresh Atlantic salmon grilled to perfection, served with seasonal vegetables and lemon butter sauce.',
      calories: 420,
      nutrition: {
        calories: 420,
        protein: 38,
        carbs: 12,
        fat: 24
      },
      dietary: ['pescatarian', 'gluten-free'],
      allergens: ['fish', 'dairy'],
      badges: ['recommended'],
      orderedCount: 312,
      rating: 4.7,
      reviewCount: 56,
      image: 'https://images.unsplash.com/photo-1519708227418-c8fd9a32b7a2?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxncmlsbGVkJTIwc2FsbW9ufGVufDF8fHx8MTc2MzkyMTQ5OXww&ixlib=rb-4.1.0&q=80&w=1080',
      reviews: [
        {
          name: 'Emma W.',
          rating: 5,
          comment: 'Perfectly cooked, moist and flavorful!',
          date: '2025-11-14'
        },
        {
          name: 'Michael B.',
          rating: 4,
          comment: 'Good quality salmon, generous portion.',
          date: '2025-11-09'
        }
      ],
      vatRate: 10,
      paidAddons: [
        { name: 'Extra Salmon (100g)', price: 8.0 },
        { name: 'Grilled Prawns', price: 6.5 },
        { name: 'Garlic Butter', price: 1.5 },
        { name: 'Hollandaise Sauce', price: 2.0 }
      ],
      freeAddons: [
        'Extra Lemon',
        'Fresh Dill',
        'Capers'
      ],
      removableItems: [
        'Butter Sauce',
        'Vegetables',
        'Lemon'
      ],
      translations: {
        de: {
          name: 'Gegrilltes Lachsfilet',
          description: 'Frischer Atlantiklachs perfekt gegrillt, serviert mit Saisongemüse und Zitronen-Butter-Sauce.',
          paidAddons: ['Extra Lachs (100g)', 'Gegrillte Garnelen', 'Knoblauchbutter', 'Sauce Hollandaise'],
          freeAddons: ['Extra Zitrone', 'Frischer Dill', 'Kapern'],
          removableItems: ['Buttersauce', 'Gemüse', 'Zitrone']
        },
        it: {
          name: 'Filetto di Salmone Grigliato',
          description: 'Salmone atlantico fresco grigliato alla perfezione, servito con verdure di stagione e salsa al burro e limone.',
          paidAddons: ['Salmone Extra (100g)', 'Gamberoni Grigliati', 'Burro all\'Aglio', 'Salsa Olandese'],
          freeAddons: ['Limone Extra', 'Aneto Fresco', 'Capperi'],
          removableItems: ['Salsa al Burro', 'Verdure', 'Limone']
        },
        fr: {
          name: 'Filet de Saumon Grillé',
          description: 'Saumon atlantique frais grillé à la perfection, servi avec légumes de saison et sauce au beurre citronné.',
          paidAddons: ['Saumon Supplémentaire (100g)', 'Crevettes Grillées', 'Beurre à l\'Ail', 'Sauce Hollandaise'],
          freeAddons: ['Citron Supplémentaire', 'Aneth Frais', 'Câpres'],
          removableItems: ['Sauce au Beurre', 'Légumes', 'Citron']
        },
        es: {
          name: 'Filete de Salmón a la Parrilla',
          description: 'Salmón atlántico fresco asado a la perfección, servido con verduras de temporada y salsa de mantequilla y limón.',
          paidAddons: ['Salmón Extra (100g)', 'Langostinos a la Parrilla', 'Mantequilla de Ajo', 'Salsa Holandesa'],
          freeAddons: ['Limón Extra', 'Eneldo Fresco', 'Alcaparras'],
          removableItems: ['Salsa de Mantequilla', 'Verduras', 'Limón']
        }
      }
    },
    {
      id: 'item_caesar',
      name: 'Classic Caesar Salad',
      category: 'salads',
      price: 11.5,
      description: 'Crisp romaine lettuce with Caesar dressing, croutons, and shaved Parmesan.',
      calories: 280,
      nutrition: {
        calories: 280,
        protein: 8,
        carbs: 18,
        fat: 20
      },
      dietary: ['vegetarian'],
      allergens: ['gluten', 'dairy', 'eggs', 'fish'],
      badges: ['most-ordered'],
      orderedCount: 398,
      rating: 4.6,
      reviewCount: 62,
      image: 'https://images.unsplash.com/photo-1550304943-4f24f54ddde9?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxjYWVzYXIlMjBzYWxhZHxlbnwxfHx8fDE3NjM5MDI0MDh8MA&ixlib=rb-4.1.0&q=80&w=1080',
      reviews: [
        {
          name: 'Laura P.',
          rating: 5,
          comment: 'Best Caesar salad I\'ve had in Vienna!',
          date: '2025-11-13'
        },
        {
          name: 'Alex R.',
          rating: 4,
          comment: 'Fresh and tasty, could use more dressing.',
          date: '2025-11-07'
        }
      ],
      vatRate: 10,
      translations: {
        de: {
          name: 'Klassischer Caesar Salat',
          description: 'Knackiger Römersalat mit Caesar-Dressing, Croutons und gehobeltem Parmesan.'
        },
        it: {
          name: 'Insalata Caesar Classica',
          description: 'Lattuga romana croccante con condimento Caesar, crostini e scaglie di parmigiano.'
        },
        fr: {
          name: 'Salade César Classique',
          description: 'Laitue romaine croquante avec sauce César, croûtons et parmesan râpé.'
        },
        es: {
          name: 'Ensalada César Clásica',
          description: 'Lechuga romana crujiente con aderezo César, crutones y parmesano rallado.'
        }
      }
    },
    {
      id: 'item_3',
      name: 'Spaghetti Carbonara',
      category: 'mains',
      price: 16.5,
      description: 'Classic Roman pasta with guanciale, eggs, Pecorino Romano, and black pepper.',
      calories: 580,
      nutrition: {
        calories: 580,
        protein: 24,
        carbs: 68,
        fat: 24
      },
      dietary: [],
      allergens: ['gluten', 'dairy', 'eggs'],
      badges: ['most-ordered'],
      orderedCount: 521,
      rating: 4.7,
      reviewCount: 62,
      image: 'https://images.unsplash.com/photo-1588013273468-315fd88ea34c?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxjYXJib25hcmElMjBwYXN0YXxlbnwxfHx8fDE3NjM4MjgyOTd8MA&ixlib=rb-4.1.0&q=80&w=1080',
      reviews: [
        {
          name: 'Elena R.',
          rating: 5,
          comment: 'Perfect carbonara! Creamy without cream, exactly as it should be.',
          date: '2025-11-14'
        },
        {
          name: 'David P.',
          rating: 4,
          comment: 'Very good, generous portion size.',
          date: '2025-11-11'
        }
      ],
      modifiers: [
        {
          name: 'Extra cheese',
          price: 2.0
        },
        {
          name: 'Add truffle oil',
          price: 4.0
        }
      ],
      vatRate: 10,
      translations: {
        de: {
          name: 'Spaghetti Carbonara',
          description: 'Klassische römische Pasta mit Guanciale, Eiern, Pecorino Romano und schwarzem Pfeffer.',
          modifiers: ['Extra Käse', 'Trüffelöl hinzufügen']
        },
        it: {
          name: 'Spaghetti alla Carbonara',
          description: 'Pasta romana classica con guanciale, uova, Pecorino Romano e pepe nero.',
          modifiers: ['Formaggio Extra', 'Aggiungi Olio al Tartufo']
        },
        fr: {
          name: 'Spaghetti Carbonara',
          description: 'Pâtes romaines classiques avec guanciale, œufs, Pecorino Romano et poivre noir.',
          modifiers: ['Fromage Supplémentaire', 'Ajouter Huile de Truffe']
        },
        es: {
          name: 'Espagueti Carbonara',
          description: 'Pasta romana clásica con guanciale, huevos, Pecorino Romano y pimienta negra.',
          modifiers: ['Queso Extra', 'Agregar Aceite de Trufa']
        }
      }
    },
    {
      id: 'item_4',
      name: 'Margherita Pizza',
      category: 'mains',
      price: 14.0,
      description: 'Wood-fired pizza with San Marzano tomatoes, fresh mozzarella, and basil.',
      calories: 680,
      nutrition: {
        calories: 680,
        protein: 28,
        carbs: 88,
        fat: 24
      },
      dietary: ['vegetarian'],
      allergens: ['gluten', 'dairy'],
      badges: ['vegetarian', 'most-ordered'],
      orderedCount: 698,
      rating: 4.9,
      reviewCount: 89,
      image: 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxtYXJnaGVyaXRhJTIwcGl6emF8ZW58MXx8fHwxNzYzODkwMDM2fDA&ixlib=rb-4.1.0&q=80&w=1080',
      reviews: [
        {
          name: 'Anna B.',
          rating: 5,
          comment: 'Best pizza in town! The crust is perfect.',
          date: '2025-11-13'
        },
        {
          name: 'Luca F.',
          rating: 5,
          comment: 'Reminds me of home in Naples!',
          date: '2025-11-09'
        }
      ],
      vatRate: 10,
      translations: {
        de: {
          name: 'Pizza Margherita',
          description: 'Holzofenpizza mit San Marzano Tomaten, frischem Mozzarella und Basilikum.'
        },
        it: {
          name: 'Pizza Margherita',
          description: 'Pizza cotta nel forno a legna con pomodori San Marzano, mozzarella fresca e basilico.'
        },
        fr: {
          name: 'Pizza Margherita',
          description: 'Pizza au feu de bois avec tomates San Marzano, mozzarella fraîche et basilic.'
        },
        es: {
          name: 'Pizza Margherita',
          description: 'Pizza al horno de leña con tomates San Marzano, mozzarella fresca y albahaca.'
        }
      }
    },
    {
      id: 'item_5',
      name: 'Tiramisu',
      category: 'desserts',
      price: 7.5,
      description: 'Classic Italian dessert with coffee-soaked ladyfingers and mascarpone cream.',
      calories: 450,
      nutrition: {
        calories: 450,
        protein: 8,
        carbs: 48,
        fat: 24
      },
      dietary: ['vegetarian'],
      allergens: ['gluten', 'dairy', 'eggs'],
      badges: ['chefs-pick'],
      orderedCount: 234,
      rating: 4.6,
      reviewCount: 34,
      image: 'https://images.unsplash.com/photo-1714385905983-6f8e06fffae1?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHx0aXJhbWlzdSUyMGRlc3NlcnR8ZW58MXx8fHwxNzYzODQ2OTA2fDA&ixlib=rb-4.1.0&q=80&w=1080',
      reviews: [
        {
          name: 'Julia W.',
          rating: 5,
          comment: 'Heaven on a plate! Not too sweet, perfect balance.',
          date: '2025-11-11'
        },
        {
          name: 'Roberto C.',
          rating: 4,
          comment: 'Good but I prefer it with more coffee flavor.',
          date: '2025-11-07'
        }
      ],
      vatRate: 10,
      translations: {
        de: {
          name: 'Tiramisu',
          description: 'Klassisches italienisches Dessert mit kaffeegetränkten Löffelbiskuits und Mascarpone-Creme.'
        },
        it: {
          name: 'Tiramisù',
          description: 'Classico dessert italiano con savoiardi imbevuti di caffè e crema di mascarpone.'
        },
        fr: {
          name: 'Tiramisu',
          description: 'Dessert italien classique avec biscuits à la cuillère imbibés de café et crème mascarpone.'
        },
        es: {
          name: 'Tiramisú',
          description: 'Postre italiano clásico con bizcochos de soletilla empapados en café y crema de mascarpone.'
        }
      }
    },
    {
      id: 'item_panna',
      name: 'Panna Cotta',
      category: 'desserts',
      price: 6.5,
      description: 'Silky smooth Italian custard with berry compote.',
      calories: 320,
      nutrition: {
        calories: 320,
        protein: 6,
        carbs: 35,
        fat: 18
      },
      dietary: ['vegetarian', 'gluten-free'],
      allergens: ['dairy'],
      badges: [],
      orderedCount: 187,
      rating: 4.5,
      reviewCount: 29,
      image: 'https://images.unsplash.com/photo-1488477181946-6428a0291777?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxwYW5uYSUyMGNvdHRhfGVufDF8fHx8MTc2MzkwMzM0NHww&ixlib=rb-4.1.0&q=80&w=1080',
      reviews: [
        {
          name: 'Nina S.',
          rating: 5,
          comment: 'Light and delicious, perfect after a heavy meal.',
          date: '2025-11-12'
        }
      ],
      vatRate: 10,
      translations: {
        de: {
          name: 'Panna Cotta',
          description: 'Seidig glatte italienische Creme mit Beerenkompott.'
        },
        it: {
          name: 'Panna Cotta',
          description: 'Dolce italiano setoso e cremoso con composta di frutti di bosco.'
        },
        fr: {
          name: 'Panna Cotta',
          description: 'Crème italienne soyeuse et lisse avec compote de baies.'
        },
        es: {
          name: 'Panna Cotta',
          description: 'Crema italiana sedosa y suave con compota de bayas.'
        }
      }
    },
    {
      id: 'item_6',
      name: 'Prosecco DOC',
      category: 'drinks',
      price: 6.5,
      description: 'Sparkling wine from Veneto region. Served chilled.',
      calories: 98,
      nutrition: {
        calories: 98,
        protein: 0,
        carbs: 2,
        fat: 0
      },
      dietary: ['vegan', 'gluten-free'],
      allergens: ['sulfites'],
      badges: [],
      orderedCount: 156,
      rating: 4.4,
      reviewCount: 18,
      image: 'https://images.unsplash.com/photo-1620421381420-e7fa4a041b15?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxwcm9zZWNjbyUyMHdpbmV8ZW58MXx8fHwxNzYzOTI4NDk2fDA&ixlib=rb-4.1.0&q=80&w=1080',
      reviews: [
        {
          name: 'Christina S.',
          rating: 4,
          comment: 'Nice and crisp, good value.',
          date: '2025-11-10'
        }
      ],
      vatRate: 20,
      translations: {
        de: {
          name: 'Prosecco DOC',
          description: 'Schaumwein aus der Region Venetien. Gut gekühlt serviert.'
        },
        it: {
          name: 'Prosecco DOC',
          description: 'Vino frizzante della regione Veneto. Servito freddo.'
        },
        fr: {
          name: 'Prosecco DOC',
          description: 'Vin mousseux de la région de Vénétie. Servi frais.'
        },
        es: {
          name: 'Prosecco DOC',
          description: 'Vino espumoso de la región de Véneto. Servido frío.'
        }
      }
    }
  ]
};