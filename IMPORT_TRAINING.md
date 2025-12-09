# Importing Initial Training Data

You have initial training data in JSON format. Here's how to import it:

## Option 1: Automatic Import (Recommended)

1. Save your training JSON file as `storage/app/initial_training.json`
2. The classifier will automatically detect and load it on first use
3. It will be converted to the internal brain format and saved as `storage/app/classifier_brain.json`

## Option 2: Manual Import Command

1. Save your training JSON file anywhere (e.g., `storage/app/initial_training.json`)
2. Run the import command:
   ```bash
   php artisan classifier:import-training
   ```
   
   Or specify a custom path:
   ```bash
   php artisan classifier:import-training /path/to/your/training.json
   ```

## Training Data Format

Your training JSON should have this structure:
```json
{
    "category_counts": {
        "Climate Change": 6,
        "Economic Justice": 8,
        "Reproductive Rights": 5,
        "LGBTQIA+": 5,
        "Immigration": 5
    },
    "word_counts": {
        "Climate Change": { "word1": count1, ... },
        "Economic Justice": { "word1": count1, ... },
        ...
    },
    "total_documents": 29,
    "vocabulary_size": 4842,
    "stop_words": ["word1", "word2", ...]
}
```

**Note:** The classifier supports these classifications: Climate Change, Economic Justice, Reproductive Rights, LGBTQIA+, and Immigration. Any other categories in your training data will be ignored.

## Verification

After importing, you can verify the brain was loaded correctly by checking:
```bash
# The brain file should exist
ls -lh storage/app/classifier_brain.json

# Check the file size (should be substantial if training data was loaded)
```

The classifier will use this training data to classify new articles, and will continue learning from new articles as they are processed.

