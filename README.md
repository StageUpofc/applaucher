# GB Launcher Ecosystem

## Estrutura do Projeto

```
GB/
├── launcher/          # Projeto Android (Kotlin)
│   └── app/
│       └── src/main/
│           ├── java/com/gb/launcher/
│           └── res/
├── admin/             # Painel Admin PHP
│   ├── api.php        # API JSON para a Launcher
│   ├── index.php      # Painel de administração
│   ├── auth.php       # Autenticação
│   ├── upload.php     # Upload de imagens
│   ├── db.php         # Conexão com banco de dados
│   ├── assets/        # CSS, JS, imagens do painel
│   └── uploads/       # Imagens enviadas
└── database/
    └── schema.sql     # Script SQL do banco de dados
```

## Parte 1 - Launcher Android
- Interface em tiles (mosaicos) responsivos
- Conteúdo carregado dinamicamente via API JSON
- Campo para configurar URL do painel admin

## Parte 2 - Painel Admin PHP
- Gerenciar logo da launcher
- Gerenciar wallpaper
- Gerenciar apps (nome, pacote, ícone)
- API JSON para a Launcher
