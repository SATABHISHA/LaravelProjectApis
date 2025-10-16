#!/bin/bash
echo "�� Laravel File Unlock Script"
echo "⏰ $(date)"

# Remove extended attributes from all PHP files
echo "Removing extended attributes..."
find . -name "*.php" -exec xattr -c {} \; 2>/dev/null || true

# Remove immutable flags  
echo "Removing immutable flags..."
find . -type f -exec chflags nouchg {} \; 2>/dev/null || true

# Set file permissions
echo "Setting permissions..."
find . -name "*.php" -exec chmod 666 {} \; 2>/dev/null || true
find . -name "*.js" -exec chmod 666 {} \; 2>/dev/null || true
find . -name "*.json" -exec chmod 666 {} \; 2>/dev/null || true

# Fix ownership
echo "Fixing ownership..."
chown -R $(whoami):staff . 2>/dev/null || true

# Make artisan executable
chmod +x artisan 2>/dev/null || true

# Test key files
echo ""
echo "File Status Check:"
for file in "routes/api.php" "app/Http/Controllers/Api/PaymentController.php"; do
    if [ -f "$file" ] && [ -w "$file" ]; then
        echo "✅ $file - WRITABLE"
    elif [ -f "$file" ]; then
        echo "❌ $file - LOCKED"
    else
        echo "⚠️  $file - NOT FOUND"
    fi
done

# Remove any .htaccess
if [ -f ".htaccess" ]; then
    rm -f .htaccess
    echo "🧹 Removed .htaccess file"
fi

echo "🎉 Unlock complete!"
